<?php

namespace Tests\Fixtures\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Test-only controller that exposes every response helper on the base
 * Controller. Registered against ad-hoc routes inside feature tests.
 */
class ResponseHelperTestController extends Controller
{
    public function ok(): JsonResponse
    {
        return $this->respondOk(['foo' => 'bar'], 'all good');
    }

    public function okDataOnly(): JsonResponse
    {
        return $this->respondOk(['foo' => 'bar']);
    }

    public function okEmpty(): JsonResponse
    {
        return $this->respondOk();
    }

    public function created(): JsonResponse
    {
        return $this->respondCreated(['id' => 1]);
    }

    public function accepted(): JsonResponse
    {
        return $this->respondAccepted(['job_id' => 'abc']);
    }

    public function noContent(): JsonResponse
    {
        return $this->respondNoContent();
    }

    public function badRequest(): JsonResponse
    {
        return $this->respondBadRequest();
    }

    public function unauthorized(): JsonResponse
    {
        return $this->respondUnauthorized();
    }

    public function forbidden(): JsonResponse
    {
        return $this->respondForbidden();
    }

    public function forbiddenCustom(): JsonResponse
    {
        return $this->respondForbidden('You shall not pass.');
    }

    public function notFound(): JsonResponse
    {
        return $this->respondNotFound();
    }

    public function methodNotAllowed(): JsonResponse
    {
        return $this->respondMethodNotAllowed();
    }

    public function conflict(): JsonResponse
    {
        return $this->respondConflict(null, ['email' => ['already taken']]);
    }

    public function unprocessable(): JsonResponse
    {
        return $this->respondUnprocessable(['email' => ['required']]);
    }

    public function tooManyRequests(): JsonResponse
    {
        return $this->respondTooManyRequests(retryAfter: 30);
    }

    public function serverError(): JsonResponse
    {
        return $this->respondServerError();
    }

    public function genericError(): JsonResponse
    {
        return $this->respondError(418, "I'm a teapot.");
    }

    public function paginated(): JsonResponse
    {
        $paginator = new LengthAwarePaginator(
            items: [['id' => 1], ['id' => 2]],
            total: 12,
            perPage: 2,
            currentPage: 1,
            options: ['path' => '/test/paginated'],
        );

        return $this->respondPaginated($paginator);
    }
}
