<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchTripRequest;
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

        return response()->json([
            'data' => $trips,
        ]);
    }
}
