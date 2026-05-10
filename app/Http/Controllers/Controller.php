<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * 200 OK. Existing behavior preserved: omits keys when arguments are null.
     */
    protected function respondOk(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return $this->buildSuccess($data, $message, $code);
    }

    /**
     * 201 Created. Falls back to the configured default message when null.
     */
    protected function respondCreated(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->buildSuccess($data, $message ?? $this->defaultMessage('created'), 201);
    }

    /**
     * 202 Accepted. For async work that has been queued.
     */
    protected function respondAccepted(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->buildSuccess($data, $message ?? $this->defaultMessage('accepted'), 202);
    }

    /**
     * 204 No Content. Empty body — message and data are intentionally omitted.
     */
    protected function respondNoContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * 200 OK with paginator unwrapped into { data, meta, links } envelope.
     */
    protected function respondPaginated(LengthAwarePaginator $paginator, ?string $message = null): JsonResponse
    {
        $lastPage = $paginator->lastPage();

        $payload = [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $lastPage,
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'path' => $paginator->path(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($lastPage),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];

        if ($message !== null) {
            $payload = ['message' => $this->translateMessage($message)] + $payload;
        }

        return response()->json($payload);
    }

    /**
     * 400 Bad Request.
     *
     * @param  array<string, mixed>|null  $errors
     */
    protected function respondBadRequest(?string $message = null, ?array $errors = null): JsonResponse
    {
        return $this->buildError(400, $message, $errors, 'bad_request');
    }

    /**
     * 401 Unauthorized (i.e. unauthenticated).
     */
    protected function respondUnauthorized(?string $message = null): JsonResponse
    {
        return $this->buildError(401, $message, null, 'unauthorized');
    }

    /**
     * 403 Forbidden.
     */
    protected function respondForbidden(?string $message = null): JsonResponse
    {
        return $this->buildError(403, $message, null, 'forbidden');
    }

    /**
     * 404 Not Found.
     */
    protected function respondNotFound(?string $message = null): JsonResponse
    {
        return $this->buildError(404, $message, null, 'not_found');
    }

    /**
     * 405 Method Not Allowed.
     */
    protected function respondMethodNotAllowed(?string $message = null): JsonResponse
    {
        return $this->buildError(405, $message, null, 'method_not_allowed');
    }

    /**
     * 409 Conflict (duplicate resource, version mismatch, etc.).
     *
     * @param  array<string, mixed>|null  $errors
     */
    protected function respondConflict(?string $message = null, ?array $errors = null): JsonResponse
    {
        return $this->buildError(409, $message, $errors, 'conflict');
    }

    /**
     * 422 Unprocessable Entity. Matches Laravel's ValidationException shape.
     *
     * @param  array<string, array<string>>  $errors
     */
    protected function respondUnprocessable(array $errors, ?string $message = null): JsonResponse
    {
        return $this->buildError(422, $message, $errors, 'unprocessable');
    }

    /**
     * 429 Too Many Requests. Sets Retry-After header when provided.
     */
    protected function respondTooManyRequests(?string $message = null, ?int $retryAfter = null): JsonResponse
    {
        $response = $this->buildError(429, $message, null, 'too_many_requests');

        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    /**
     * 500 Internal Server Error.
     */
    protected function respondServerError(?string $message = null): JsonResponse
    {
        return $this->buildError(500, $message, null, 'server_error');
    }

    /**
     * Generic error responder. Use when a status code is computed at runtime.
     *
     * @param  array<string, mixed>|null  $errors
     */
    protected function respondError(int $code, ?string $message = null, ?array $errors = null): JsonResponse
    {
        return $this->buildError($code, $message, $errors, null);
    }

    private function buildSuccess(mixed $data, ?string $message, int $code): JsonResponse
    {
        $payload = [];

        if ($message !== null) {
            $payload['message'] = $this->translateMessage($message);
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $code);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    private function buildError(int $code, ?string $message, ?array $errors, ?string $defaultKey): JsonResponse
    {
        $payload = [];

        $resolved = $message ?? $this->defaultMessage($defaultKey);

        if ($resolved !== null) {
            $payload['message'] = $this->translateMessage($resolved);
        }

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }

    private function defaultMessage(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        if (! config('boilerplate.responses.use_defaults', true)) {
            return null;
        }

        $value = config("boilerplate.responses.default_messages.{$key}");

        return is_string($value) ? $value : null;
    }

    private function translateMessage(string $message): string
    {
        $translated = __($message);

        return is_string($translated) ? $translated : $message;
    }
}
