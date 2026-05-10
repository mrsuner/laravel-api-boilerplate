<?php

namespace Tests\Feature\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class ApiExceptionRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('test/exceptions/auth', function (): never {
            throw new AuthenticationException;
        });

        Route::get('test/exceptions/authz', function (): never {
            throw new AuthorizationException('Custom denial reason.');
        });

        Route::get('test/exceptions/model-missing', function (): never {
            throw (new ModelNotFoundException)->setModel('App\\Models\\User', [99]);
        });

        Route::get('test/exceptions/abort-404', function (): never {
            abort(404);
        });

        Route::get('test/exceptions/abort-403-custom', function (): never {
            abort(403, 'You shall not pass.');
        });

        Route::get('test/exceptions/method-not-allowed', function (): never {
            abort(405);
        });

        Route::get('test/exceptions/validation', function (): never {
            throw ValidationException::withMessages([
                'email' => ['The email is required.'],
            ]);
        });

        Route::get('test/exceptions/throttle', function (): never {
            throw new TooManyRequestsHttpException(60);
        });

        Route::get('test/exceptions/teapot', function (): never {
            throw new HttpException(418, "I'm a teapot.");
        });

        Route::get('test/exceptions/runtime', function (): never {
            throw new RuntimeException('Something blew up.');
        });
    }

    public function test_authentication_exception_renders_as_401(): void
    {
        $this->getJson('test/exceptions/auth')
            ->assertStatus(401)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.unauthorized')]);
    }

    public function test_authorization_exception_renders_as_403(): void
    {
        $this->getJson('test/exceptions/authz')
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_model_not_found_renders_as_404(): void
    {
        $this->getJson('test/exceptions/model-missing')
            ->assertStatus(404)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.not_found')]);
    }

    public function test_abort_404_renders_with_default_message(): void
    {
        $this->getJson('test/exceptions/abort-404')
            ->assertStatus(404)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.not_found')]);
    }

    public function test_abort_with_custom_message_keeps_message(): void
    {
        $this->getJson('test/exceptions/abort-403-custom')
            ->assertStatus(403)
            ->assertExactJson(['message' => 'You shall not pass.']);
    }

    public function test_abort_405_renders_with_default_message(): void
    {
        $this->getJson('test/exceptions/method-not-allowed')
            ->assertStatus(405)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.method_not_allowed')]);
    }

    public function test_validation_exception_renders_as_422_with_errors(): void
    {
        $this->getJson('test/exceptions/validation')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['email']])
            ->assertJsonPath('errors.email.0', 'The email is required.');
    }

    public function test_throttle_exception_sets_retry_after_header(): void
    {
        $response = $this->getJson('test/exceptions/throttle');

        $response->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertHeader('Retry-After', '60');
    }

    public function test_arbitrary_http_exception_uses_its_message(): void
    {
        $this->getJson('test/exceptions/teapot')
            ->assertStatus(418)
            ->assertExactJson(['message' => "I'm a teapot."]);
    }

    public function test_unhandled_exception_renders_as_500(): void
    {
        $this->getJson('test/exceptions/runtime')
            ->assertStatus(500)
            ->assertExactJson(['message' => config('boilerplate.responses.default_messages.server_error')]);
    }

    public function test_unhandled_exception_includes_debug_payload_when_enabled(): void
    {
        config()->set('boilerplate.exceptions.expose_debug_in_response', true);

        $response = $this->getJson('test/exceptions/runtime');

        $response->assertStatus(500)
            ->assertJsonStructure(['message', 'debug' => ['exception', 'message', 'file', 'line']])
            ->assertJsonPath('debug.message', 'Something blew up.')
            ->assertJsonPath('debug.exception', RuntimeException::class);
    }

    public function test_renderer_skips_when_render_for_api_is_disabled(): void
    {
        config()->set('boilerplate.exceptions.render_for_api', false);
        config()->set('boilerplate.responses.default_messages.server_error', 'CUSTOM_SENTINEL_MESSAGE');

        $response = $this->getJson('test/exceptions/runtime');

        // With our renderer disabled Laravel's default takes over, so the
        // custom sentinel from our config must not appear in the response.
        $this->assertNotSame('CUSTOM_SENTINEL_MESSAGE', $response->json('message'));
    }
}
