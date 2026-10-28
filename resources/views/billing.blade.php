<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store Billing — New Order</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6f9; color: #1f2937; }
        header { background: #1e293b; color: #fff; padding: 14px 24px; font-size: 18px; font-weight: bold; }
        main { max-width: 1000px; margin: 20px auto; padding: 0 16px; display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .card { background: #fff; border: 1px solid #d1d5db; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        h3 { margin: 0 0 10px; font-size: 15px; }
        label { font-size: 12px; font-weight: bold; display: block; margin-bottom: 4px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #9ca3af; border-radius: 4px; font-size: 14px; }
        .row { display: flex; gap: 12px; }
        .row > div { flex: 1; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #e5e7eb; }
        td input, td select { padding: 6px; }
        .right { text-align: right; }
        button { border: 0; border-radius: 4px; padding: 10px 16px; font-size: 14px; font-weight: bold; cursor: pointer; color: #fff; }
        .btn-blue { background: #2563eb; }
        .btn-green { background: #16a34a; width: 100%; font-size: 16px; }
        .btn-remove { background: #dc2626; padding: 4px 8px; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .grand { font-weight: bold; font-size: 16px; border-top: 1px dashed #9ca3af; margin-top: 6px; padding-top: 8px !important; }
        .alert { background: #fffbeb; border: 1px solid #d97706; }
        .alert h3 { color: #b45309; }
        .alert ul { margin: 0; padding-left: 18px; color: #92400e; font-size: 14px; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 12px; display: none; }
        .success { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; margin-bottom: 12px; display: none; }
        .muted { color: #6b7280; font-size: 12px; }
        @media (max-width: 760px) { main { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header>Store Billing — New Order</header>

<main>
    <!-- LEFT SIDE: customer, products, payment -->
    <section>
        <div id="errorBox" class="error"></div>
        <div id="successBox" class="success"></div>

        <div class="card">
            <h3>Customer</h3>
            <div class="row">
                <div>
                    <label for="email">Email</label>
                    <input id="email" type="email" placeholder="e.g. thomas@example.com">
                </div>
                <div>
                    <label for="name">Name</label>
                    <input id="name" type="text" placeholder="auto-filled if email exists">
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Products</h3>
            <table>
                <thead>
                <tr><th>Product</th><th style="width:80px">Qty</th><th class="right">Price</th><th class="right">Tax</th><th class="right">Line Total</th><th></th></tr>
                </thead>
                <tbody id="lines"></tbody>
            </table>
            <p><button class="btn-blue" onclick="addLine()">+ Add Product</button></p>
        </div>

        <div class="card">
            <h3>Payment</h3>
            <div class="totals">
                <div><span>Subtotal</span><span id="subtotal">₹0.00</span></div>
                <div><span>Tax</span><span id="tax">₹0.00</span></div>
                <div class="grand"><span>Grand Total</span><span id="grandTotal">₹0.00</span></div>
            </div>
            <br>
            <label for="amountGiven">Amount Given by Customer</label>
            <input id="amountGiven" type="number" min="0" placeholder="₹250" oninput="showBalance()">
            <p><b>Balance to Return:</b> <span id="balance">—</span></p>
            <p class="muted" id="denominations"></p>
        </div>
    </section>

    <!-- RIGHT SIDE: low stock alert + generate bill -->
    <aside>
        <div class="card alert">
            <h3>⚠ Low Stock Alert</h3>
            <ul id="lowStockList"><li>Loading…</li></ul>
        </div>

        <button class="btn-green" onclick="generateBill()">Generate Bill</button>
        <p class="muted">Saves the order, deducts stock and queues the confirmation email.</p>

        <div class="card" id="billCard" style="display:none">
            <h3>Bill</h3>
            <div id="bill"></div>
        </div>
    </aside>
</main>

<script>
    // ---------- Simple state ----------
    let products = [];       // all products from /api/products
    let lastGrandTotal = 0;  // used for "balance to return"

    const rupees = (n) => '₹' + Number(n).toFixed(2);

    // ---------- Small helper to call the API ----------
    async function api(url, options = {}) {
        const response = await fetch(url, {
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            ...options,
        });
        const data = await response.json();
        return { ok: response.ok, status: response.status, data };
    }

    // ---------- Load data ----------
    async function loadProducts() {
        const { data } = await api('/api/products');
        products = data.data;
        document.getElementById('lines').innerHTML = '';
        addLine();
    }

    async function loadLowStock() {
        const { data } = await api('/api/products/low-stock');
        const list = document.getElementById('lowStockList');
        list.innerHTML = data.data.length
            ? data.data.map(p => `<li>${p.name} — ${p.stock} units left</li>`).join('')
            : '<li>All products are well stocked.</li>';
    }

    // When the email field loses focus, look up the customer and auto-fill the name.
    document.getElementById('email').addEventListener('blur', async (e) => {
        const email = e.target.value.trim();
        if (!email) return;
        const { ok, data } = await api('/api/orders?email=' + encodeURIComponent(email));
        if (ok) document.getElementById('name').value = data.customer.name;
    });

    // ---------- Product lines ----------
    function addLine() {
        const options = products
            .map(p => `<option value="${p.id}">${p.name} (stock ${p.stock})</option>`)
            .join('');

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select onchange="recalculate()"><option value="">-- choose --</option>${options}</select></td>
            <td><input type="number" min="1" value="1" oninput="recalculate()"></td>
            <td class="right price">—</td>
            <td class="right taxp">—</td>
            <td class="right lineTotal">—</td>
            <td><button class="btn-remove" onclick="this.closest('tr').remove(); recalculate()">x</button></td>`;
        document.getElementById('lines').appendChild(tr);
    }

    // Read all rows of the table → [{ product, quantity, row }]
    function getLines() {
        return [...document.querySelectorAll('#lines tr')].map(row => ({
            row,
            product: products.find(p => p.id == row.querySelector('select').value),
            quantity: parseInt(row.querySelector('input').value) || 0,
        }));
    }

    // Preview of the totals (the server calculates the real, final totals).
    function recalculate() {
        let subtotal = 0, tax = 0;
        getLines().forEach(({ row, product, quantity }) => {
            if (!product) return;
            const lineSubtotal = product.price * quantity;
            const lineTax = lineSubtotal * product.tax_percent / 100;
            subtotal += lineSubtotal;
            tax += lineTax;
            row.querySelector('.price').textContent = rupees(product.price);
            row.querySelector('.taxp').textContent = product.tax_percent + '%';
            row.querySelector('.lineTotal').textContent = rupees(lineSubtotal + lineTax);
        });
        showTotals(subtotal, tax, subtotal + tax);
    }

    function showTotals(subtotal, tax, grand) {
        document.getElementById('subtotal').textContent = rupees(subtotal);
        document.getElementById('tax').textContent = rupees(tax);
        document.getElementById('grandTotal').textContent = rupees(grand);
        lastGrandTotal = grand;
        showBalance();
    }

    // ---------- Balance & change (UI only) ----------
    function showBalance() {
        const given = parseFloat(document.getElementById('amountGiven').value);
        const balanceEl = document.getElementById('balance');
        const notesEl = document.getElementById('denominations');
        if (isNaN(given)) { balanceEl.textContent = '—'; notesEl.textContent = ''; return; }

        const balance = given - lastGrandTotal;
        if (balance < 0) {
            balanceEl.textContent = 'Customer still needs to pay ' + rupees(-balance);
            notesEl.textContent = '';
            return;
        }
        balanceEl.textContent = rupees(balance);
        notesEl.textContent = changeBreakdown(balance);
    }

    // e.g. 22.80 → "1×₹20 + 1×₹2 + 80 paise"
    function changeBreakdown(amount) {
        const notes = [500, 200, 100, 50, 20, 10, 5, 2, 1];
        let rest = Math.floor(amount);
        const paise = Math.round((amount - rest) * 100);
        const parts = [];
        notes.forEach(n => {
            const count = Math.floor(rest / n);
            if (count) { parts.push(`${count}×₹${n}`); rest -= count * n; }
        });
        if (paise) parts.push(`${paise} paise`);
        return parts.join(' + ');
    }

    // ---------- Submit order ----------
    async function generateBill() {
        const errorBox = document.getElementById('errorBox');
        const successBox = document.getElementById('successBox');
        errorBox.style.display = successBox.style.display = 'none';

        const items = getLines()
            .filter(l => l.product)
            .map(l => ({ product_id: l.product.id, quantity: l.quantity }));

        const { ok, data } = await api('/api/orders', {
            method: 'POST',
            body: JSON.stringify({
                email: document.getElementById('email').value.trim(),
                name: document.getElementById('name').value.trim() || null,
                items,
            }),
        });

        if (!ok) {
            // Show all validation / stock errors
            const messages = data.errors ? Object.values(data.errors).flat() : [data.message];
            errorBox.innerHTML = messages.join('<br>');
            errorBox.style.display = 'block';
            return;
        }

        const order = data.data;
        showTotals(order.subtotal, order.tax_total, order.grand_total);
        successBox.textContent = `Order ${order.order_number} saved. Confirmation email queued for ${order.customer.email}.`;
        successBox.style.display = 'block';
        showBill(order);

        // Refresh stock numbers after the order
        await loadProducts();
        await loadLowStock();
    }

    function showBill(order) {
        const rows = order.items.map(i =>
            `<tr><td>${i.product_name} × ${i.quantity}</td><td class="right">${rupees(i.line_total)}</td></tr>`
        ).join('');
        document.getElementById('bill').innerHTML = `
            <p class="muted">${order.order_number}<br>${order.customer.name} (${order.customer.email})<br>${order.created_at}</p>
            <table>${rows}
                <tr><td>Subtotal</td><td class="right">${rupees(order.subtotal)}</td></tr>
                <tr><td>Tax</td><td class="right">${rupees(order.tax_total)}</td></tr>
                <tr><th>Grand Total</th><th class="right">${rupees(order.grand_total)}</th></tr>
            </table>`;
        document.getElementById('billCard').style.display = 'block';
    }

    // ---------- Start ----------
    loadProducts();
    loadLowStock();
</script>
</body>
</html>
