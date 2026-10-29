# Store Order & Inventory Mini-System

A small Laravel 12 app for a retail counter: record customer orders against a product catalog and keep stock in sync.

- REST API to create orders, see a customer's order history and list low-stock products
- Queued job that "sends" an order-confirmation email (written to the log)
- Stock deduction that is safe under concurrent requests (no overselling)
- A simple billing screen at `/` based on the wireframe
- PHPUnit feature and unit tests

---

## 1. Setup

**Requirements:** PHP 8.2+ (with `pdo_sqlite`), Composer. MySQL is optional.

```bash
cd store-order-system
composer install
cp .env.example .env            # Windows: copy .env.example .env
php artisan key:generate

# pgsql
create database in pgsql


php artisan migrate --seed      # creates tables + sample products & customers
```

### Run it (2 terminals)

```bash
php artisan serve               # terminal 1 → http://localhost:8000
php artisan queue:work          # terminal 2 → processes the email jobs
```

Open http://localhost:8000 for the billing screen.
After placing an order, the fake email shows up in `storage/logs/laravel.log`.

### Run the tests

```bash
php artisan test
```

Tests use an in-memory SQLite database (see `phpunit.xml`), so they don't touch your data.

### Using MySQL instead (optional)

In `.env`:

```
# pgsql
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=store_orders
DB_USERNAME=postgres
DB_PASSWORD=admin@123
```

Then run `php artisan migrate:fresh --seed`.

---

## 2. API

All endpoints are under `/api` and return JSON.

| Method | URL | What it does |
|---|---|---|
| `POST` | `/api/orders` | Create an order |
| `GET` | `/api/orders?email=...` | Order history for a customer |
| `GET` | `/api/products/low-stock?threshold=10` | Products with stock **below** the threshold |
| `GET` | `/api/products` | All products (used by the billing screen) |

### Create an order

```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
        "email": "thomas@example.com",
        "name": "Thomas",
        "items": [
          { "product_id": 1, "quantity": 2 },
          { "product_id": 2, "quantity": 5 }
        ]
      }'
```

**201 Created**

```json
{
  "data": {
    "id": 1,
    "order_number": "ORD-20261028-K3J9QX",
    "customer": { "id": 1, "name": "Thomas", "email": "thomas@example.com" },
    "items": [
      { "product_id": 1, "product_name": "Colgate Toothpaste", "quantity": 2,
        "unit_price": 50, "tax_percent": 8, "line_subtotal": 100, "line_tax": 8, "line_total": 108 }
    ],
    "subtotal": 150, "tax_total": 12, "grand_total": 162,
    "created_at": "2026-10-28 18:30:00"
  }
}
```

**422 – not enough stock**

```json
{
  "message": "Not enough stock for Bread. Requested 5, available 3.",
  "errors": { "items": ["Not enough stock for Bread. Requested 5, available 3."] },
  "product_id": 3, "requested": 5, "available": 3
}
```

**422 – validation error** (standard Laravel format, e.g. missing email, quantity 0, unknown product).

### Order history

```bash
curl "http://localhost:8000/api/orders?email=thomas@example.com"
```

Returns `{ "customer": {...}, "orders": [...] }`, newest order first. Unknown email → `404`.

### Low stock

```bash
curl "http://localhost:8000/api/products/low-stock"              # uses LOW_STOCK_THRESHOLD from .env (default 10)
curl "http://localhost:8000/api/products/low-stock?threshold=5"  # override per request
```

Returns `{ "data": [...products...], "threshold": 10 }`, lowest stock first.

---

## 3. Project structure (where to look)

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── OrderController.php      # thin: validate → call service → return resource
│   │   └── ProductController.php
│   ├── Requests/                    # form-request validation
│   │   ├── StoreOrderRequest.php
│   │   ├── OrderHistoryRequest.php
│   │   └── LowStockRequest.php
│   └── Resources/                   # JSON output format
├── Services/OrderService.php        # ★ all order logic: customer, stock, totals, job
├── Jobs/SendOrderConfirmationEmail.php  # ★ queued fake email
├── Exceptions/InsufficientStockException.php  # renders the 422 JSON
└── Models/  Customer, Product, Order, OrderItem

config/inventory.php                 # low-stock threshold
database/migrations/                 # schema
database/factories/, seeders/        # sample data
resources/views/billing.blade.php    # billing screen (plain HTML + JS)
routes/api.php, routes/web.php
tests/Feature/, tests/Unit/
```

### Request flow for "create order"

```
POST /api/orders
  → StoreOrderRequest   (validates email, name, items)
  → OrderController@store
  → OrderService::placeOrder()
        DB transaction {
           find or create customer
           for each item: atomic stock deduct  ← fails? throw → everything rolls back
           calculate line totals
           save order + order_items
        }
        dispatch SendOrderConfirmationEmail (queued)
  → OrderResource (201 JSON)
```

---

## 4. Database schema

```
customers    id, name, email (unique), timestamps
products     id, name, code (unique), price, tax_percent, stock, timestamps
orders       id, order_number (unique), customer_id → customers, subtotal, tax_total, grand_total, timestamps
order_items  id, order_id → orders, product_id → products, quantity,
             unit_price, tax_percent, line_subtotal, line_tax, line_total, timestamps
             unique(order_id, product_id)
jobs, job_batches, failed_jobs   (Laravel database queue)
```

- `order_items` is the pivot between orders and products (one order → many lines).
- Money is stored as `DECIMAL`, never float.
- Relationships: `Customer hasMany Order`, `Order belongsTo Customer`, `Order hasMany OrderItem`, `OrderItem belongsTo Product`.

---

## 5. How overselling is prevented (requirement 7)

Stock is reduced with **one atomic SQL statement**:

```sql
UPDATE products SET stock = stock - :qty
WHERE id = :id AND stock >= :qty
```

(`Product::whereKey($id)->where('stock', '>=', $qty)->decrement('stock', $qty)` in `OrderService::deductStock()`.)

The database checks and updates the row in the same step, and row-level locking makes the second request wait for the first. So with 1 unit left:

- Request A: `stock >= 1` is true → stock becomes 0 → **1 row updated → success**
- Request B: `stock >= 1` is now false → **0 rows updated → `InsufficientStockException` → 422**

Because this runs inside `DB::transaction()`, a failure on any line rolls back the whole order (other lines' stock, the order row and a newly created customer).

I chose this over `SELECT ... FOR UPDATE` + check + update because it is one query, easy to read, and works the same on MySQL, PostgreSQL and SQLite (SQLite ignores `lockForUpdate()`).

**Try it manually** (with seed data, "Last One Chocolate" has stock 1 — check its id with `GET /api/products`):

```bash
for i in 1 2; do
  curl -s -X POST http://localhost:8000/api/orders -H "Content-Type: application/json" -H "Accept: application/json" \
    -d '{"email":"race'$i'@example.com","name":"Racer '$i'","items":[{"product_id":8,"quantity":1}]}' &
done; wait
```

One request returns 201, the other 422. (`php artisan serve` handles one request at a time; for a real parallel test use MySQL + `PHP_CLI_SERVER_WORKERS=4 php artisan serve`, or Nginx/Apache.)

The automated test is `tests/Unit/StockConcurrencyTest.php`.

---

## 6. Queued email job (requirement 5)

`SendOrderConfirmationEmail` implements `ShouldQueue`, so it is stored in the `jobs` table and the API responds immediately. `php artisan queue:work` runs it, and it writes the email text to `storage/logs/laravel.log`:

```
[FAKE EMAIL] Order confirmation sent
To: thomas@example.com
Subject: Your order ORD-20261028-K3J9QX is confirmed
...
```

- It is dispatched **after** the transaction commits, so it never runs for a rolled-back order.
- It retries 3 times with a 10-second back-off; failed jobs go to `failed_jobs`.

---

## 7. Tests

| File | Covers |
|---|---|
| `Feature/CreateOrderTest.php` | Happy path with totals + stock + queued job; **insufficient stock**; multi-line rollback; buying the exact remaining stock; existing customer reuse; name required for new customer; validation (bad email, unknown product, qty 0, duplicate product); price snapshot |
| `Feature/OrderHistoryTest.php` | Newest-first history, other customers excluded, empty history, unknown email 404, email required |
| `Feature/LowStockTest.php` | Default threshold from config, `?threshold=` override, "below" means `<` not `<=`, invalid threshold |
| `Unit/StockConcurrencyTest.php` | Race simulation: stale read loses to the atomic update; stock never goes negative |
| `Unit/SendOrderConfirmationEmailTest.php` | Job is queued (`ShouldQueue`), job writes the fake email to the log |
| `Feature/BillingPageTest.php` | Billing page loads |

---

## 8. Assumptions & design decisions

1. **Customer identification** – customers are identified by email. If the email exists, that customer is used (the name in the request is ignored, so a typo can't rename an existing customer). If it is new, `name` is required.
2. **Duplicate products in one order** – rejected with a validation error ("change the quantity instead"). This keeps one line per product and matches the `unique(order_id, product_id)` index.
3. **Tax** – each product has its own tax %. Tax is calculated per line: `line_tax = round(qty × price × tax% / 100, 2)`. Order totals are sums of the lines. Prices are tax-exclusive.
4. **Price snapshot** – `unit_price` and `tax_percent` are copied into `order_items`, so old orders don't change when a product price changes.
5. **"Below" the threshold** – means `stock < threshold`. A product with exactly 10 units is not low stock when the threshold is 10.
6. **Low-stock threshold** – configured in `.env` (`LOW_STOCK_THRESHOLD`, default 10) via `config/inventory.php`, and can be overridden per request with `?threshold=`.
7. **Order history endpoint** – `GET /api/orders?email=...` (query string, because emails in URL paths need extra encoding). Unknown email → 404; known customer with no orders → empty list.
8. **Insufficient stock status code** – `422`, the same as validation errors, so the client handles all "bad order" cases in one place. The response tells which product and how many are available.
9. **No authentication** – the brief doesn't mention users/login, so the API is open. In a real app I would add Sanctum.
10. **Currency** – Indian Rupees (₹), as in the wireframe. Money is `DECIMAL(10/12, 2)`.
11. **Billing screen extras** – "Amount given" and "Balance to return" (with note breakdown) are calculated in the browser only and not stored, since they are not in the data scope. The wireframe's "emails PDF to customer" is covered by the queued fake email (no PDF).
12. **Order number** – `ORD-YYYYMMDD-XXXXXX` (random 6 chars), unique in the database, easier to read on a bill than an id.

---

