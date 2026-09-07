<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PingController extends Controller
{
    /**
     * Liveness probe for the API behind nginx. Returns the standard success
     * envelope (PRD §22.3) so clients can verify routing and response shape.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'service' => 'opsflow-api',
                'version' => config('app.version') ?? '1.0.0',
                'status' => 'ok',
            ],
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
            ],
        ]);
    }
}
