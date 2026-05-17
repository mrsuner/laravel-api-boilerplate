<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Requests\Me\ListDevicesRequest;
use App\Http\Resources\DeviceResource;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * @group Me
 */
class DeviceController extends Controller
{
    /**
     * List my push devices.
     *
     * Returns the push-notification devices registered to the authenticated
     * user. Raw push tokens are never returned.
     *
     * @authenticated
     *
     * @queryParam provider string Filter by push transport. One of `fcm`, `expo`, `apns`. Example: fcm
     * @queryParam sort string One of `last_used_at`, `-last_used_at`, `created_at`, `-created_at`. Defaults to `-last_used_at`. Example: -last_used_at
     * @queryParam per_page integer 1–100. Defaults to 15. Example: 20
     * @queryParam page integer 1+. Example: 1
     *
     * @response 200 {"data": [{"id": "01HX...", "platform": "ios", "provider": "fcm"}], "meta": {"current_page": 1}, "links": {}}
     */
    public function index(ListDevicesRequest $request): JsonResponse
    {
        $user = $this->currentUser($request);

        $query = UserDevice::query()->where('user_id', $user->getKey());

        if (($provider = $request->string('provider')->toString()) !== '') {
            $query->where('provider', $provider);
        }

        $this->applySort($query, $request->string('sort', '-last_used_at')->toString());

        $paginator = $query
            ->paginate($this->resolvePerPage($request))
            ->withQueryString()
            ->through(fn (UserDevice $device): DeviceResource => new DeviceResource($device));

        return $this->respondPaginated($paginator);
    }

    /**
     * @param  Builder<UserDevice>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $query->orderBy($column, $descending ? 'desc' : 'asc');
    }
}
