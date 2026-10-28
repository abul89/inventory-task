<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown when a product does not have enough stock for an order.
 * Laravel calls render() automatically and returns a clean 422 JSON error.
 */
class InsufficientStockException extends Exception
{
    public function __construct(
        public Product $product,
        public int $requested,
        public int $available,
    ) {
        parent::__construct(
            "Not enough stock for {$product->name}. Requested {$requested}, available {$available}."
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors'  => [
                'items' => [$this->getMessage()],
            ],
            'product_id' => $this->product->id,
            'requested'  => $this->requested,
            'available'  => $this->available,
        ], 422);
    }
}
