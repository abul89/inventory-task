<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Don't run the job for real, just record that it was pushed to the queue.
        Queue::fake();
    }

    public function test_it_creates_an_order_calculates_totals_and_deducts_stock(): void
    {
        $toothpaste = Product::factory()->create(['price' => 50, 'tax_percent' => 8, 'stock' => 10]);
        $biscuit    = Product::factory()->create(['price' => 10, 'tax_percent' => 8, 'stock' => 20]);

        $response = $this->postJson('/api/orders', [
            'email' => 'thomas@example.com',
            'name'  => 'Thomas',
            'items' => [
                ['product_id' => $toothpaste->id, 'quantity' => 2], // 100 + 8 tax = 108
                ['product_id' => $biscuit->id,    'quantity' => 5], //  50 + 4 tax =  54
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer.email', 'thomas@example.com')
            ->assertJsonPath('data.subtotal', 150.0)
            ->assertJsonPath('data.tax_total', 12.0)
            ->assertJsonPath('data.grand_total', 162.0)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.line_total', 108.0);

        // Stock was deducted
        $this->assertSame(8, $toothpaste->fresh()->stock);
        $this->assertSame(15, $biscuit->fresh()->stock);

        // Order and lines were saved
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);

        // Confirmation email job was queued
        Queue::assertPushed(SendOrderConfirmationEmail::class, function ($job) {
            return $job->order->customer->email === 'thomas@example.com';
        });
    }

    public function test_it_rejects_the_order_when_stock_is_not_enough(): void
    {
        $product = Product::factory()->create(['name' => 'Bread', 'stock' => 3]);

        $response = $this->postJson('/api/orders', [
            'email' => 'thomas@example.com',
            'name'  => 'Thomas',
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Not enough stock for Bread. Requested 5, available 3.')
            ->assertJsonPath('available', 3);

        $this->assertSame(3, $product->fresh()->stock); // stock unchanged
        $this->assertDatabaseCount('orders', 0);
        Queue::assertNothingPushed();
    }

    public function test_nothing_is_saved_if_only_one_line_has_insufficient_stock(): void
    {
        $inStock    = Product::factory()->create(['stock' => 10]);
        $notInStock = Product::factory()->create(['stock' => 1]);

        $this->postJson('/api/orders', [
            'email' => 'thomas@example.com',
            'name'  => 'Thomas',
            'items' => [
                ['product_id' => $inStock->id,    'quantity' => 4], // this one is fine...
                ['product_id' => $notInStock->id, 'quantity' => 2], // ...but this one fails
            ],
        ])->assertStatus(422);

        // The first line's stock deduction was rolled back too
        $this->assertSame(10, $inStock->fresh()->stock);
        $this->assertSame(1, $notInStock->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('customers', 0); // customer creation rolled back as well
    }

    public function test_buying_exactly_the_remaining_stock_works_and_leaves_zero(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->postJson('/api/orders', [
            'email' => 'thomas@example.com',
            'name'  => 'Thomas',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_an_existing_customer_is_reused_and_name_is_optional(): void
    {
        $customer = Customer::factory()->create(['email' => 'priya@example.com', 'name' => 'Priya']);
        $product  = Product::factory()->create(['stock' => 5]);

        $this->postJson('/api/orders', [
            'email' => 'priya@example.com', // no name sent
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.customer.name', 'Priya');

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_name_is_required_for_a_new_customer(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/orders', [
            'email' => 'new@example.com',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_validates_the_request(): void
    {
        $product = Product::factory()->create();

        // Missing everything
        $this->postJson('/api/orders', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'items']);

        // Bad values
        $this->postJson('/api/orders', [
            'email' => 'not-an-email',
            'name'  => 'X',
            'items' => [
                ['product_id' => 99999, 'quantity' => 1],      // product does not exist
                ['product_id' => $product->id, 'quantity' => 0], // quantity too small
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'items.0.product_id', 'items.1.quantity']);

        // Same product twice
        $this->postJson('/api/orders', [
            'email' => 'thomas@example.com',
            'name'  => 'Thomas',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_keeps_the_price_at_the_time_of_purchase(): void
    {
        $product = Product::factory()->create(['price' => 100, 'tax_percent' => 0, 'stock' => 5]);

        $this->postJson('/api/orders', [
            'email' => 'thomas@example.com',
            'name'  => 'Thomas',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        // Price changes later...
        $product->update(['price' => 200]);

        // ...but the saved order still shows the old price
        $order = Order::with('items')->first();
        $this->assertEquals(100, $order->items[0]->unit_price);
        $this->assertEquals(100, $order->grand_total);
    }
}
