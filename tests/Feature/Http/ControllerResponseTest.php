<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Controllers\ResponseHelperTestController;
use Tests\TestCase;

class ControllerResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $controller = ResponseHelperTestController::class;

        Route::get('test/responses/ok', [$controller, 'ok']);
        Route::get('test/responses/ok-data', [$controller, 'okDataOnly']);
        Route::get('test/responses/ok-empty', [$controller, 'okEmpty']);
        Route::get('test/responses/created', [$controller, 'created']);
        Route::get('test/responses/accepted', [$controller, 'accepted']);
        Route::get('test/responses/no-content', [$controller, 'noContent']);
        Route::get('test/responses/bad-request', [$controller, 'badRequest']);
        Route::get('test/responses/unauthorized', [$controller, 'unauthorized']);
        Route::get('test/responses/forbidden', [$controller, 'forbidden']);
        Route::get('test/responses/forbidden-custom', [$controller, 'forbiddenCustom']);
        Route::get('test/responses/not-found', [$controller, 'notFound']);
        Route::get('test/responses/method-not-allowed', [$controller, 'methodNotAllowed']);
        Route::get('test/responses/conflict', [$controller, 'conflict']);
        Route::get('test/responses/unprocessable', [$controller, 'unprocessable']);
        Route::get('test/responses/too-many', [$controller, 'tooManyRequests']);
        Route::get('test/responses/server-error', [$controller, 'serverError']);
        Route::get('test/responses/generic-error', [$controller, 'genericError']);
        Route::get('test/responses/paginated', [$controller, 'paginated']);
    }

    public function test_respond_ok_with_data_and_message(): void
    {
        $this->getJson('test/responses/ok')
            ->assertStatus(200)
            ->assertExactJson(['message' => 'all good', 'data' => ['foo' => 'bar']]);
    }

    public function test_respond_ok_with_data_only_omits_message_key(): void
    {
        $this->getJson('test/responses/ok-data')
            ->assertStatus(200)
            ->assertExactJson(['data' => ['foo' => 'bar']]);
    }

    public function test_respond_ok_with_no_args_returns_empty_object(): void
    {
        $this->getJson('test/responses/ok-empty')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_respond_created_uses_default_message(): void
    {
        $this->getJson('test/responses/created')
            ->assertStatus(201)
            ->assertJson([
                'message' => config('boilerplate.responses.default_messages.created'),
                'data' => ['id' => 1],
            ]);
    }

    public function test_respond_accepted_uses_default_message(): void
    {
        $this->getJson('test/responses/accepted')
            ->assertStatus(202)
            ->assertJson([
                'message' => config('boilerplate.responses.default_messages.accepted'),
                'data' => ['job_id' => 'abc'],
            ]);
    }

    public function test_respond_no_content_returns_empty_204(): void
    {
        $response = $this->getJson('test/responses/no-content');

        $response->assertStatus(204);
        $this->assertSame('', $response->getContent());
    }

    public function test_respond_bad_request_uses_default_message(): void
    {
        $this->getJson('test/responses/bad-request')
            ->assertStatus(400)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.bad_request')]);
    }

    public function test_respond_unauthorized_uses_default_message(): void
    {
        $this->getJson('test/responses/unauthorized')
            ->assertStatus(401)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.unauthorized')]);
    }

    public function test_respond_forbidden_uses_default_message(): void
    {
        $this->getJson('test/responses/forbidden')
            ->assertStatus(403)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.forbidden')]);
    }

    public function test_respond_forbidden_with_custom_message(): void
    {
        $this->getJson('test/responses/forbidden-custom')
            ->assertStatus(403)
            ->assertExactJson(['message' => 'You shall not pass.']);
    }

    public function test_respond_not_found_uses_default_message(): void
    {
        $this->getJson('test/responses/not-found')
            ->assertStatus(404)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.not_found')]);
    }

    public function test_respond_method_not_allowed_uses_default_message(): void
    {
        $this->getJson('test/responses/method-not-allowed')
            ->assertStatus(405)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.method_not_allowed')]);
    }

    public function test_respond_conflict_includes_errors(): void
    {
        $this->getJson('test/responses/conflict')
            ->assertStatus(409)
            ->assertExactJson([
                'message' => config('boilerplate.responses.default_messages.conflict'),
                'errors' => ['email' => ['already taken']],
            ]);
    }

    public function test_respond_unprocessable_includes_errors(): void
    {
        $this->getJson('test/responses/unprocessable')
            ->assertStatus(422)
            ->assertExactJson([
                'message' => config('boilerplate.responses.default_messages.unprocessable'),
                'errors' => ['email' => ['required']],
            ]);
    }

    public function test_respond_too_many_requests_sets_retry_after_header(): void
    {
        $response = $this->getJson('test/responses/too-many');

        $response->assertStatus(429)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.too_many_requests')])
            ->assertHeader('Retry-After', '30');
    }

    public function test_respond_server_error_uses_default_message(): void
    {
        $this->getJson('test/responses/server-error')
            ->assertStatus(500)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.server_error')]);
    }

    public function test_respond_error_with_arbitrary_status_code(): void
    {
        $this->getJson('test/responses/generic-error')
            ->assertStatus(418)
            ->assertExactJson(['message' => "I'm a teapot."]);
    }

    public function test_respond_paginated_returns_data_meta_links_envelope(): void
    {
        $this->getJson('test/responses/paginated')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total', 'path'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonCount(2, 'data');
    }

    public function test_use_defaults_disabled_omits_message(): void
    {
        config()->set('boilerplate.responses.use_defaults', false);

        $this->getJson('test/responses/forbidden')
            ->assertStatus(403)
            ->assertExactJson([]);
    }
}
