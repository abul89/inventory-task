<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LowStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /** GET /api/products  — all products (used by the billing page dropdown) */
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(Product::orderBy('name')->get());
    }

    /** GET /api/products/low-stock?threshold=10  — products with stock BELOW the threshold */
    public function lowStock(LowStockRequest $request): AnonymousResourceCollection
    {
        $threshold = $request->threshold();

        return ProductResource::collection(Product::lowStock($threshold)->get())
            ->additional(['threshold' => $threshold]);
    }
}
