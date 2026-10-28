<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per product line in an order.
     *
     * unit_price and tax_percent are COPIED from the product at the time of
     * the order, so old bills never change if the product price changes later.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_percent', 5, 2);
            $table->decimal('line_subtotal', 12, 2); // quantity * unit_price
            $table->decimal('line_tax', 12, 2);      // line_subtotal * tax_percent / 100
            $table->decimal('line_total', 12, 2);    // line_subtotal + line_tax
            $table->timestamps();

            $table->unique(['order_id', 'product_id']); // a product appears once per order
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
