<?php

namespace App\Http\Controllers;

use App\Services\LogisticsMapPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogisticsMapDataController extends Controller
{
    public function __construct(
        private readonly LogisticsMapPayloadService $mapPayload,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(
            $this->mapPayload->buildForUser($request->user())
        );
    }
}
