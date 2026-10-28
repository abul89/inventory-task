<?php

namespace Tests\Unit;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Requirement 7: two orders for the LAST unit must not both succeed.
 *
 * PHPUnit runs in one process, so we simulate the race:
 * "Request A" reads the product (stock = 1), then "Request B" buys the
 * last unit first. When A tries to deduct with its old (stale) data,
 * the atomic UPDATE ... WHERE stock >= qty must refuse it.
 */
class StockConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_of_two_racing_orders_gets_the_last_unit(): void
    {
        Queue::fake();
        $service = app(OrderService::class);
        $product = Product::factory()->create(['stock' => 1]);

        // Request A has read the product and still thinks stock = 1
        $staleProductSeenByRequestA = Product::find($product->id);
        $this->assertSame(1, $staleProductSeenByRequestA->stock);

        // Request B wins the race and buys the last unit
        $service->placeOrder('b@example.com', 'B', [['product_id' => $product->id, 'quantity' => 1]]);

        // Request A now tries to deduct using its stale data → must fail cleanly
        try {
            $service->deductStock($staleProductSeenByRequestA, 1);
            $this->fail('Request A should not be able to buy the last unit.');
        } catch (InsufficientStockException $e) {
            $this->assertSame(0, $e->available);
        }

        // No overselling: stock is 0, never -1
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_second_order_for_last_unit_fails_through_the_service(): void
    {
        Queue::fake();
        $service = app(OrderService::class);
        $product = Product::factory()->create(['stock' => 1]);

        $service->placeOrder('a@example.com', 'A', [['product_id' => $product->id, 'quantity' => 1]]);

        $this->expectException(InsufficientStockException::class);
        $service->placeOrder('b@example.com', 'B', [['product_id' => $product->id, 'quantity' => 1]]);
    }
}
