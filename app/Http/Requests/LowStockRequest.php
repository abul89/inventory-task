<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validates: GET /api/products/low-stock?threshold=5  (threshold is optional) */
class LowStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'threshold' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** Use ?threshold=... if given, otherwise the value from config/inventory.php */
    public function threshold(): int
    {
        return $this->integer('threshold', config('inventory.low_stock_threshold'));
    }
}
