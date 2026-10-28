<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();                 // e.g. "COL-100"
            $table->decimal('price', 10, 2);                  // price per unit
            $table->decimal('tax_percent', 5, 2)->default(0); // e.g. 18.00 = 18%
            $table->unsignedInteger('stock')->default(0);     // stock on hand
            $table->timestamps();

            $table->index('stock'); // makes the low-stock query fast
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
