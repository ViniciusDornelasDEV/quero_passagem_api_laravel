<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class StopResource extends JsonResource
{
    /**
     * @return array{id: mixed, name: mixed, type: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'type' => data_get($this->resource, 'type'),
        ];
    }
}
