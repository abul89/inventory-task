<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderHistoryRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * POST /api/orders
     * Validation is done by StoreOrderRequest, logic by OrderService.
     */
    public function store(StoreOrderRequest $request, OrderService $orderService): JsonResponse
    {
        $order = $orderService->placeOrder(
            $request->input('email'),
            $request->input('name'),
            $request->input('items'),
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/orders?email=thomas@example.com
     * Returns the customer and all their orders (newest first).
     */
    public function history(OrderHistoryRequest $request): JsonResponse
    {
        $customer = Customer::where('email', $request->input('email'))->first();

        if (! $customer) {
            return response()->json(['message' => 'No customer found with this email.'], 404);
        }

        $orders = $customer->orders()
            ->with('items.product')
            ->latest('id')
            ->get();

        return response()->json([
            'customer' => new CustomerResource($customer),
            'orders'   => OrderResource::collection($orders),
        ]);
    }
}
