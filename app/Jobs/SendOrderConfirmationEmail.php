<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Simulates sending an order-confirmation email.
 *
 * Because it implements ShouldQueue, Laravel puts it in the "jobs" table
 * and the API responds immediately. A worker (php artisan queue:work)
 * picks it up later. No real SMTP – we just write the email to the log file.
 */
class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;      // retry up to 3 times if it fails

    public int $backoff = 10;   // wait 10 seconds between retries

    public function __construct(public Order $order)
    {
    }

    public function handle(): void
    {
        $order = $this->order->load(['customer', 'items.product']);

        $lines = $order->items->map(fn ($item) => sprintf(
            '  - %s x %d = Rs %.2f',
            $item->product->name,
            $item->quantity,
            $item->line_total,
        ))->implode("\n");

        $email = <<<TEXT
        To: {$order->customer->email}
        Subject: Your order {$order->order_number} is confirmed

        Hi {$order->customer->name},
        Thank you for shopping with us!

        {$lines}

          Subtotal:    Rs {$order->subtotal}
          Tax:         Rs {$order->tax_total}
          Grand Total: Rs {$order->grand_total}
        TEXT;

        Log::info("[FAKE EMAIL] Order confirmation sent\n".$email);
    }
}
