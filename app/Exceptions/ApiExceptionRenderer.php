<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Translates framework exceptions into the standard JSON envelope used by
 * App\Http\Controllers\Controller response helpers, so thrown errors and
 * returned errors share a single shape.
 */
class ApiExceptionRenderer
{
    /**
     * Default message keys (in config/boilerplate.php) for known status codes.
     *
     * @var array<int, string>
     */
    private const CODE_TO_KEY = [
        400 => 'bad_request',
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        422 => 'unprocessable',
        429 => 'too_many_requests',
        500 => 'server_error',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $this->shouldRender($request)) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return $this->renderValidation($e);
        }

        if ($e instanceof AuthenticationException) {
            return $this->renderByCode(401, $e->getMessage());
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return $this->renderThrottle($e);
        }

        if ($e instanceof HttpExceptionInterface) {
            // ModelNotFoundException is converted to NotFoundHttpException by
            // Laravel's prepareException step, which would otherwise leak the
            // model class and id through the exception message. Force the
            // configured default message for that case.
            if ($this->wrapsModelNotFound($e)) {
                return $this->renderByCode(404);
            }

            return $this->renderHttp($e);
        }

        return $this->renderUnhandled($e);
    }

    private function shouldRender(Request $request): bool
    {
        if (! config('boilerplate.exceptions.render_for_api', true)) {
            return false;
        }

        return $request->expectsJson() || $request->is('api/*');
    }

    private function renderValidation(ValidationException $e): JsonResponse
    {
        $message = $this->translate($e->getMessage())
            ?? $this->defaultMessageFor(422)
            ?? 'The given data was invalid.';

        return response()->json([
            'message' => $message,
            'errors' => $e->errors(),
        ], $e->status);
    }

    private function renderThrottle(TooManyRequestsHttpException $e): JsonResponse
    {
        $payload = $this->payloadForCode(429, $e->getMessage());
        $response = response()->json($payload, 429);

        $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    private function renderByCode(int $code, ?string $exceptionMessage = null): JsonResponse
    {
        return response()->json($this->payloadForCode($code, $exceptionMessage), $code);
    }

    private function renderHttp(HttpExceptionInterface $e): JsonResponse
    {
        $code = $e->getStatusCode();
        $payload = $this->payloadForCode($code, $e->getMessage());

        return response()->json($payload, $code);
    }

    private function wrapsModelNotFound(HttpExceptionInterface $e): bool
    {
        if ($e->getStatusCode() !== 404) {
            return false;
        }

        return $e->getPrevious() instanceof ModelNotFoundException;
    }

    private function renderUnhandled(Throwable $e): JsonResponse
    {
        $payload = $this->payloadForCode(500);

        if (config('boilerplate.exceptions.expose_debug_in_response', false)) {
            $payload['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return response()->json($payload, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Build a payload for a given status code, preferring the framework-provided
     * exception message when meaningful, falling back to the configured default.
     *
     * @return array<string, mixed>
     */
    private function payloadForCode(int $code, ?string $exceptionMessage = null): array
    {
        $payload = [];

        $message = $this->preferExceptionMessage($code, $exceptionMessage)
            ?? $this->defaultMessageFor($code);

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return $payload;
    }

    /**
     * Use the exception's own message when it adds information beyond the
     * status code (e.g. a custom abort('Custom reason')). Empty strings and
     * the generic Symfony default ("No message") are filtered out.
     */
    private function preferExceptionMessage(int $code, ?string $exceptionMessage): ?string
    {
        if ($exceptionMessage === null || trim($exceptionMessage) === '') {
            return null;
        }

        return $this->translate($exceptionMessage);
    }

    private function defaultMessageFor(int $code): ?string
    {
        $key = self::CODE_TO_KEY[$code] ?? null;

        if ($key === null) {
            return null;
        }

        if (! config('boilerplate.responses.use_defaults', true)) {
            return null;
        }

        $value = config("boilerplate.responses.default_messages.{$key}");

        return is_string($value) ? $this->translate($value) : null;
    }

    private function translate(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $translated = __($message);

        return is_string($translated) ? $translated : $message;
    }
}
