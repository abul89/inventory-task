<?php

namespace Tests\Feature;

use Tests\TestCase;

class BillingPageTest extends TestCase
{
    public function test_billing_page_loads(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Store Billing');
    }
}
