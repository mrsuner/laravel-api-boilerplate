<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Devices\StoreDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\UserDevice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Devices
 */
class DeviceController extends Controller
{
    /**
     * Register a push device.
     *
     * Registers (or refreshes) the calling user's device for push
     * notifications. The push token is the identity: re-sending an existing
     * token — even from a different account — transfers ownership so a
     * recycled device never receives the previous user's notifications.
     * Idempotent; safe to call on every app launch.
     *
     * @authenticated
     *
     * @bodyParam platform string required OS family. One of `ios`, `android`, `web`. Example: ios
     * @bodyParam provider string required Push transport. One of `fcm`, `expo`, `apns`. Example: fcm
     * @bodyParam push_token string required The provider-issued push token. Example: fGc1...token
     * @bodyParam device_id string Optional stable client device identifier. Example: 7B3F2A10-...
     * @bodyParam device_name string Optional human-readable device label. Example: John's iPhone
     * @bodyParam app_version string Optional client app version. Example: 1.4.2
     *
     * @response 201 {"data": {"id": "01HX...", "platform": "ios", "provider": "fcm", "device_name": "John's iPhone", "last_used_at": "2026-05-17T10:00:00+00:00"}}
     */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $this->ensureModuleEnabled();

        $device = UserDevice::registerForUser($request->user(), [
            'platform' => $request->string('platform')->toString(),
            'provider' => $request->string('provider')->toString(),
            'push_token' => $request->string('push_token')->toString(),
            'device_id' => $request->input('device_id'),
            'device_name' => $request->input('device_name'),
            'app_version' => $request->input('app_version'),
        ]);

        return $this->respondCreated(new DeviceResource($device));
    }

    /**
     * Unregister a push device.
     *
     * Removes one of the caller's registered devices. Only the owner may
     * delete a device.
     *
     * @authenticated
     *
     * @urlParam device string required The device id. Example: 01HX...
     *
     * @response 204
     */
    public function destroy(Request $request, UserDevice $device): JsonResponse
    {
        $this->ensureModuleEnabled();
        $this->authorizeOwn($request, $device);

        $device->delete();

        return $this->respondNoContent();
    }

    private function ensureModuleEnabled(): void
    {
        if (! config('boilerplate.push.enabled', true)) {
            abort(404);
        }
    }

    private function authorizeOwn(Request $request, UserDevice $device): void
    {
        if ($device->user_id !== $request->user()->getKey()) {
            throw new AuthorizationException;
        }
    }
}
