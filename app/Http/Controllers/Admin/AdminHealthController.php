<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @group Internal Admin — Health
 *
 * Liveness probe for the core infrastructure dependencies. Returns 200 when
 * every check passes and 503 if any single check fails.
 */
class AdminHealthController extends Controller
{
    /**
     * Health check
     *
     * @authenticated
     */
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::connection()->getPdo()),
            'cache' => $this->check(function (): bool {
                Cache::put('_health', 1, 5);

                return Cache::get('_health') === 1;
            }),
            'queue' => $this->check(fn () => Queue::size()),
            'storage' => $this->check(function (): bool {
                Storage::disk('local')->put('_health', '1');
                Storage::disk('local')->delete('_health');

                return true;
            }),
        ];

        $allOk = ! in_array('fail', $checks, true);

        return response()->json([
            'data' => [
                'status' => $allOk ? 'ok' : 'fail',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
        ], $allOk ? 200 : 503);
    }

    private function check(callable $probe): string
    {
        try {
            return $probe() === false ? 'fail' : 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }
}
