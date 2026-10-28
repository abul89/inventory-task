<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_customers_orders_newest_first(): void
    {
        Queue::fake();
        $product = Product::factory()->create(['stock' => 10]);

        $service = app(OrderService::class);
        $first  = $service->placeOrder('thomas@example.com', 'Thomas', [['product_id' => $product->id, 'quantity' => 1]]);
        $second = $service->placeOrder('thomas@example.com', null, [['product_id' => $product->id, 'quantity' => 2]]);

        // An order from someone else must NOT appear
        $service->placeOrder('other@example.com', 'Other', [['product_id' => $product->id, 'quantity' => 1]]);

        $this->getJson('/api/orders?email=thomas@example.com')
            ->assertOk()
            ->assertJsonPath('customer.name', 'Thomas')
            ->assertJsonCount(2, 'orders')
            ->assertJsonPath('orders.0.order_number', $second->order_number)
            ->assertJsonPath('orders.1.order_number', $first->order_number)
            ->assertJsonPath('orders.0.items.0.quantity', 2);
    }

    public function test_customer_with_no_orders_gets_an_empty_list(): void
    {
        Customer::factory()->create(['email' => 'priya@example.com']);

        $this->getJson('/api/orders?email=priya@example.com')
            ->assertOk()
            ->assertJsonCount(0, 'orders');
    }

    public function test_unknown_email_returns_404(): void
    {
        $this->getJson('/api/orders?email=nobody@example.com')
            ->assertNotFound();
    }

    public function test_email_is_required(): void
    {
        $this->getJson('/api/orders')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
