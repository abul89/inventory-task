<?php

namespace Tests\Unit;

use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendOrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_is_queued_not_run_directly(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(SendOrderConfirmationEmail::class));
    }

    public function test_job_writes_the_fake_email_to_the_log(): void
    {
        Queue::fake(); // create the order without running the job
        $product = Product::factory()->create(['name' => 'Milk 1L', 'stock' => 5]);
        $order = app(OrderService::class)->placeOrder('thomas@example.com', 'Thomas', [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'thomas@example.com')
                && str_contains($message, $order->order_number)
                && str_contains($message, 'Milk 1L x 2'));

        // Run the job by hand
        (new SendOrderConfirmationEmail($order))->handle();
    }
}
