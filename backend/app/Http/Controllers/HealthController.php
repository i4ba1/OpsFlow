<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Internal health probe (PRD §46.4). Must only be reachable from the
 * infrastructure layer (nginx internal network); it intentionally has no auth
 * so load balancers and orchestrators can poll it.
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $services = [
            'app' => true,
            'database' => $this->databaseIsHealthy(),
            'cache' => $this->cacheIsHealthy(),
        ];

        $status = in_array(false, $services, true) ? 503 : 200;

        return response()->json([
            'status' => $status === 200 ? 'ok' : 'degraded',
            'services' => array_map(
                static fn (bool $healthy): string => $healthy ? 'ok' : 'error',
                $services,
            ),
            'timestamp' => now()->toIso8601String(),
        ], $status);
    }

    private function databaseIsHealthy(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function cacheIsHealthy(): bool
    {
        try {
            $key = 'health-check-'.bin2hex(random_bytes(4));
            Cache::put($key, true, 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return $value === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
