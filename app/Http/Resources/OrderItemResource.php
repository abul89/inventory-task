<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id'    => $this->product_id,
            'product_name'  => $this->product?->name,
            'product_code'  => $this->product?->code,
            'quantity'      => $this->quantity,
            'unit_price'    => (float) $this->unit_price,
            'tax_percent'   => (float) $this->tax_percent,
            'line_subtotal' => (float) $this->line_subtotal,
            'line_tax'      => (float) $this->line_tax,
            'line_total'    => (float) $this->line_total,
        ];
    }
}
