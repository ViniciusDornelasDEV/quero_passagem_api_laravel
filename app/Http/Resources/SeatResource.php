<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'seat_number' => data_get($this->resource, 'seat_number'),
            'occupied' => (bool) data_get($this->resource, 'occupied', false),
            'type' => data_get($this->resource, 'type'),
            'position' => [
                'x' => data_get($this->resource, 'x'),
                'y' => data_get($this->resource, 'y'),
            ],
        ];
    }
}