<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // A few realistic products (some with LOW stock so the alert shows something)
        $products = [
            ['name' => 'Colgate Toothpaste', 'code' => 'COL-100',  'price' => 50,  'tax_percent' => 8,  'stock' => 40],
            ['name' => 'Parle-G Biscuit',    'code' => 'PAR-G01',  'price' => 10,  'tax_percent' => 8,  'stock' => 120],
            ['name' => 'Bread',              'code' => 'BRD-400',  'price' => 45,  'tax_percent' => 0,  'stock' => 4],
            ['name' => 'Milk 1L',            'code' => 'MLK-1L',   'price' => 62,  'tax_percent' => 0,  'stock' => 9],
            ['name' => 'Eggs (12)',          'code' => 'EGG-12',   'price' => 84,  'tax_percent' => 0,  'stock' => 2],
            ['name' => 'Tata Salt 1kg',      'code' => 'SLT-1KG',  'price' => 28,  'tax_percent' => 5,  'stock' => 60],
            ['name' => 'Surf Excel 1kg',     'code' => 'SRF-1KG',  'price' => 140, 'tax_percent' => 18, 'stock' => 25],
            ['name' => 'Last One Chocolate', 'code' => 'CHO-LAST', 'price' => 20,  'tax_percent' => 12, 'stock' => 1],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['code' => $product['code']], $product);
        }

        // Plus some random products from the factory
        Product::factory()->count(5)->create();
    }
}
