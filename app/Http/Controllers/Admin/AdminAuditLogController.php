<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminAuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @group Internal Admin — Audit Logs
 *
 * Read-only access to the append-only audit trail with filtering by user,
 * event and date range.
 */
class AdminAuditLogController extends Controller
{
    /**
     * List audit logs
     *
     * @authenticated
     *
     * @queryParam user_id string Filter by acting user id.
     * @queryParam event string Filter by event name (partial match). Example: admin.login
     * @queryParam from date Inclusive lower bound (Y-m-d). Example: 2026-01-01
     * @queryParam to date Inclusive upper bound (Y-m-d). Example: 2026-12-31
     * @queryParam per_page integer Results per page (max 100). Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user');

        if ($userId = $request->string('user_id')->trim()->value()) {
            $query->forUser($userId);
        }

        if ($event = $request->string('event')->trim()->value()) {
            $query->where('event', 'like', "%{$event}%");
        }

        if ($from = $request->string('from')->trim()->value()) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $request->string('to')->trim()->value()) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $logs = $query->latest()
            ->paginate($this->resolvePerPage($request))
            ->through(fn (AuditLog $log) => (new AdminAuditLogResource($log))->resolve());

        return $this->respondPaginated($logs);
    }

    /**
     * Show audit log
     *
     * @authenticated
     */
    public function show(AuditLog $log): JsonResponse
    {
        return $this->respondOk(
            AdminAuditLogResource::make($log->load('user'))
        );
    }

    /**
     * List a user's audit logs
     *
     * @authenticated
     *
     * @queryParam per_page integer Results per page (max 100). Example: 20
     */
    public function forUser(User $user, Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('user')
            ->forUser($user->id)
            ->latest()
            ->paginate($this->resolvePerPage($request))
            ->through(fn (AuditLog $log) => (new AdminAuditLogResource($log))->resolve());

        return $this->respondPaginated($logs);
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 20);

        if ($perPage < 1) {
            $perPage = 20;
        }

        return min($perPage, 100);
    }
}
