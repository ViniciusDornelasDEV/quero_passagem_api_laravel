<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $data,
        ], $status);
    }
}
