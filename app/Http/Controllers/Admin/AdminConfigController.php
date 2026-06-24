<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateConfigRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * @group Internal Admin — Runtime Config
 *
 * Exposes a whitelisted, boolean-toggleable subset of the boilerplate runtime
 * config. Changes are in-memory only and do not persist across restarts —
 * persistence is intentionally out of scope for v1.
 */
class AdminConfigController extends Controller
{
    /**
     * Show config
     *
     * Returns the admin-safe subset of runtime config.
     *
     * @authenticated
     */
    public function show(): JsonResponse
    {
        $whitelist = (array) config('boilerplate.admin.config_whitelist', []);

        $data = [];
        foreach ($whitelist as $key) {
            $data[$key] = config("boilerplate.{$key}");
        }

        return $this->respondOk($data);
    }

    /**
     * Update config
     *
     * Applies whitelisted boolean toggles to the in-memory runtime config.
     *
     * @authenticated
     */
    public function update(UpdateConfigRequest $request): JsonResponse
    {
        $whitelist = (array) config('boilerplate.admin.config_whitelist', []);

        foreach (Arr::dot($request->validated()) as $key => $value) {
            if (in_array($key, $whitelist, true)) {
                config(["boilerplate.{$key}" => $value]);
            }
        }

        audit_log('admin.config.updated', null, [
            'metadata' => $request->validated(),
            'user' => auth()->user(),
        ]);

        return $this->respondOk(message: 'Config updated.');
    }
}
