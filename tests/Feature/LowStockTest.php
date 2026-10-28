<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_configured_threshold_by_default(): void
    {
        config(['inventory.low_stock_threshold' => 10]);

        Product::factory()->create(['name' => 'Eggs', 'stock' => 2]);
        Product::factory()->create(['name' => 'Milk', 'stock' => 9]);
        Product::factory()->create(['name' => 'Exactly Ten', 'stock' => 10]); // NOT below 10
        Product::factory()->create(['name' => 'Rice', 'stock' => 50]);

        $this->getJson('/api/products/low-stock')
            ->assertOk()
            ->assertJsonPath('threshold', 10)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Eggs') // lowest stock first
            ->assertJsonPath('data.1.name', 'Milk');
    }

    public function test_threshold_can_be_passed_in_the_query_string(): void
    {
        Product::factory()->create(['stock' => 2]);
        Product::factory()->create(['stock' => 9]);

        $this->getJson('/api/products/low-stock?threshold=5')
            ->assertOk()
            ->assertJsonPath('threshold', 5)
            ->assertJsonCount(1, 'data');
    }

    public function test_threshold_must_be_a_positive_number(): void
    {
        $this->getJson('/api/products/low-stock?threshold=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['threshold']);
    }
}
