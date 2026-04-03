<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\SearchTripRequest;
use App\Http\Resources\TripResource;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;

class TripController extends Controller
{
    public function __construct(
        private readonly TripService $tripService,
    ) {
    }

    public function search(SearchTripRequest $request): JsonResponse
    {
        $trips = $this->tripService->search($request->validated());

        return ApiResponse::success(TripResource::collection($trips));
    }
}
