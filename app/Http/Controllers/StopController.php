<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\StopResource;
use App\Services\StopService;
use Illuminate\Http\JsonResponse;

class StopController extends Controller
{
    public function __construct(
        private readonly StopService $stopService,
    ) {
    }

    public function index(): JsonResponse
    {
        $stops = $this->stopService->getStops();

        return ApiResponse::success(StopResource::collection($stops));
    }
}
