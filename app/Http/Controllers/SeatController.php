<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\SeatRequest;
use App\Http\Resources\SeatResource;
use App\Services\SeatService;
use Illuminate\Http\JsonResponse;

class SeatController extends Controller
{
    public function __construct(
        private readonly SeatService $seatService,
    ) {
    }

    public function index(SeatRequest $request): JsonResponse
    {
        $seats = $this->seatService->getSeats($request->validated());

        return ApiResponse::success(SeatResource::collection($seats));
    }
}
