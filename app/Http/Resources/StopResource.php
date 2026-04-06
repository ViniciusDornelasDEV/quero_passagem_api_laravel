<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $substops = $this->resource['substops'] ?? null;

        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'url' => data_get($this->resource, 'url'),
            'type' => data_get($this->resource, 'type'),
            'substops' => StopResource::collection(
                is_array($substops) ? $substops : [],
            ),
        ];
    }
}
