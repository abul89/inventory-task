<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'code'        => $this->code,
            'price'       => (float) $this->price,
            'tax_percent' => (float) $this->tax_percent,
            'stock'       => $this->stock,
        ];
    }
}
