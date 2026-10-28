<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::updateOrCreate(['email' => 'thomas@example.com'], ['name' => 'Thomas']);
        Customer::updateOrCreate(['email' => 'priya@example.com'],  ['name' => 'Priya Sharma']);

        Customer::factory()->count(5)->create();
    }
}
