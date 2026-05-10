<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Files\UploadFileRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Services\Files\FileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Files
 */
class FileController extends Controller
{
    public function __construct(private readonly FileService $files) {}

    /**
     * Upload a file.
     *
     * Returns the created file record with a TTL. Persist the returned id on
     * a parent record and call FileService::claim() to opt out of cleanup.
     *
     * @authenticated
     *
     * @bodyParam file file required The file to upload.
     * @bodyParam visibility string Optional. `public` or `private`. Defaults to the configured visibility.
     * @bodyParam meta object Optional. Free-form metadata stored alongside the record.
     *
     * @response 201 {"data": {"id": "01HX...", "client_name": "photo.jpg", "size": 12345, "expires_at": "2026-05-11T05:00:00+00:00"}}
     */
    public function store(UploadFileRequest $request): JsonResponse
    {
        $this->ensureModuleEnabled();

        // Resolve via Sanctum guard explicitly because the upload route does
        // not apply auth middleware (so anonymous traffic can pass through
        // when the config flag is set).
        $user = $request->user() ?: auth('sanctum')->user();

        if ($user === null && ! config('boilerplate.files.allow_anonymous_upload', false)) {
            throw new AuthenticationException;
        }

        $file = $this->files->store($request->file('file'), [
            'visibility' => $request->input('visibility'),
            'meta' => $request->input('meta'),
            'uploader_type' => $user?->getMorphClass(),
            'uploader_id' => $user?->getKey(),
        ]);

        return $this->respondCreated(new FileResource($file));
    }

    /**
     * Show file metadata.
     *
     * @authenticated
     */
    public function show(Request $request, File $file): JsonResponse
    {
        $this->ensureModuleEnabled();
        $this->authorizeRead($request, $file);

        return $this->respondOk(new FileResource($file));
    }

    /**
     * Stream the file contents to the client.
     *
     * @authenticated
     */
    public function download(Request $request, File $file): StreamedResponse
    {
        $this->ensureModuleEnabled();
        $this->authorizeRead($request, $file);

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            abort(404);
        }

        return $disk->download($file->path, $file->client_name, [
            'Content-Type' => $file->mime_type,
        ]);
    }

    /**
     * Delete a file (soft delete + remove from disk).
     *
     * @authenticated
     */
    public function destroy(Request $request, File $file): JsonResponse
    {
        $this->ensureModuleEnabled();
        $this->authorizeOwn($request, $file);

        $this->files->delete($file);

        return $this->respondNoContent();
    }

    private function ensureModuleEnabled(): void
    {
        if (! config('boilerplate.files.enabled', true)) {
            abort(404);
        }
    }

    private function authorizeRead(Request $request, File $file): void
    {
        $user = $request->user();

        if ($file->visibility === 'public' && $user !== null) {
            return;
        }

        $this->authorizeOwn($request, $file);
    }

    private function authorizeOwn(Request $request, File $file): void
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if ($file->uploader_id !== $user->getKey() || $file->uploader_type !== $user->getMorphClass()) {
            throw new AuthorizationException;
        }
    }
}
