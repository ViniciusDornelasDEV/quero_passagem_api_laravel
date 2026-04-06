<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\StopResource;
use App\Services\StopService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class StopController extends Controller
{
    public function __construct(
        private readonly StopService $stopService,
    ) {}

    public function index(): JsonResponse
    {
        try {
            $stops = $this->stopService->getStops();
        } catch (RuntimeException) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'type' => 'cache_not_ready',
                    'message' => 'Stops cache not initialized',
                ],
            ], 503);
        }

        return ApiResponse::success(
            StopResource::collection($stops),
            200,
            ['allowedStates' => config('queropassagem.allowed_states', [])],
        );
    }
}
