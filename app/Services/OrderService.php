<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * All the business logic for placing an order lives here,
 * so the controller stays small and this class is easy to test.
 */
class OrderService
{
    /**
     * Create an order.
     *
     * @param  string  $email  Customer email (used to find or create the customer)
     * @param  string|null  $name  Customer name (only used when the customer is new)
     * @param  array  $items  List of ['product_id' => 1, 'quantity' => 2]
     *
     * @throws InsufficientStockException
     */
    public function placeOrder(string $email, ?string $name, array $items): Order
    {
        // Everything inside the transaction is "all or nothing".
        // If any product is out of stock, the exception rolls back every change
        // (no order is saved and no stock is deducted).
        $order = DB::transaction(function () use ($email, $name, $items) {

            // 1. Find the customer by email, or create a new one.
            $customer = Customer::firstOrCreate(
                ['email' => $email],
                ['name' => $name],
            );

            // 2. Load all products of this order in one query.
            $products = Product::whereIn('id', array_column($items, 'product_id'))
                ->get()
                ->keyBy('id');

            // 3. For every line: deduct stock safely and calculate the amounts.
            $lines = [];
            foreach ($items as $item) {
                $product  = $products[$item['product_id']];
                $quantity = (int) $item['quantity'];

                $this->deductStock($product, $quantity);

                $lines[] = $this->calculateLine($product, $quantity);
            }

            // 4. Save the order with its totals, then save its lines.
            $order = $customer->orders()->create([
                'order_number' => $this->generateOrderNumber(),
                'subtotal'     => round(array_sum(array_column($lines, 'line_subtotal')), 2),
                'tax_total'    => round(array_sum(array_column($lines, 'line_tax')), 2),
                'grand_total'  => round(array_sum(array_column($lines, 'line_total')), 2),
            ]);

            $order->items()->createMany($lines);

            return $order;
        });

        // 5. Send the confirmation email in the background (queued job).
        //    We dispatch AFTER the transaction is committed, so the job never
        //    runs for an order that was rolled back.
        SendOrderConfirmationEmail::dispatch($order);

        return $order->load(['customer', 'items.product']);
    }

    /**
     * Reduce stock in ONE atomic SQL query:
     *
     *   UPDATE products SET stock = stock - :qty
     *   WHERE id = :id AND stock >= :qty
     *
     * The database checks the stock and reduces it at the same moment, so two
     * requests can never both buy the last unit. If 0 rows were updated, it
     * means there was not enough stock → we throw an exception.
     */
    public function deductStock(Product $product, int $quantity): void
    {
        $updatedRows = Product::whereKey($product->id)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);

        if ($updatedRows === 0) {
            $available = (int) Product::whereKey($product->id)->value('stock');

            throw new InsufficientStockException($product, $quantity, $available);
        }
    }

    /**
     * Calculate the money values for one product line.
     * Price and tax % are copied so old bills stay correct if prices change later.
     */
    private function calculateLine(Product $product, int $quantity): array
    {
        $unitPrice  = (float) $product->price;
        $taxPercent = (float) $product->tax_percent;

        $lineSubtotal = round($unitPrice * $quantity, 2);
        $lineTax      = round($lineSubtotal * $taxPercent / 100, 2);

        return [
            'product_id'    => $product->id,
            'quantity'      => $quantity,
            'unit_price'    => $unitPrice,
            'tax_percent'   => $taxPercent,
            'line_subtotal' => $lineSubtotal,
            'line_tax'      => $lineTax,
            'line_total'    => round($lineSubtotal + $lineTax, 2),
        ];
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
