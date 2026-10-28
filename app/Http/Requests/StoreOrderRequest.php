<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the "create order" request body:
 *
 * {
 *   "email": "thomas@example.com",
 *   "name":  "Thomas",                 // required only for a NEW customer
 *   "items": [
 *     { "product_id": 1, "quantity": 2 },
 *     { "product_id": 2, "quantity": 5 }
 *   ]
 * }
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // no login in this mini-project
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],

            // Name is needed only when this email is not in the customers table yet.
            'name' => [
                Rule::requiredIf(fn () => ! Customer::where('email', $this->input('email'))->exists()),
                'nullable', 'string', 'max:255',
            ],

            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'               => 'Name is required for a new customer.',
            'items.required'              => 'Please add at least one product.',
            'items.*.product_id.distinct' => 'The same product is added twice. Please change the quantity instead.',
            'items.*.product_id.exists'   => 'The selected product does not exist.',
            'items.*.quantity.min'        => 'Quantity must be at least 1.',
        ];
    }
}
