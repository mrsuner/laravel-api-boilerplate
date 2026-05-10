# API Responses & Exception Handling

The boilerplate ships a base `Controller` with reusable JSON response helpers and a global exception renderer that converts framework exceptions into the same envelope shape — so thrown errors and intentional responses share one schema.

## Why

- Every endpoint speaks the same JSON dialect, whether it returned data or threw a `ModelNotFoundException`.
- Defaults are configurable via `config/boilerplate.php`; any project can tweak wording, disable defaults, or add i18n without touching code.
- Messages are run through Laravel's translator (`__()`) so adding a `lang/<locale>/...` file is enough to localize.

## Response Envelope

Success:
```json
{
  "message": "Resource created.",   // optional
  "data": { "...": "..." }            // optional
}
```

Error:
```json
{
  "message": "Resource not found.",
  "errors": { "email": ["already taken"] }   // optional, used by 409 / 422
}
```

Pagination:
```json
{
  "data": [ "..." ],
  "meta": { "current_page": 1, "from": 1, "last_page": 5, "per_page": 15, "to": 15, "total": 75, "path": "..." },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

## Helpers

`App\Http\Controllers\Controller` (the base class every controller extends) exposes:

### Success
| Method | Status | Notes |
|---|---|---|
| `respondOk($data = null, $message = null, $code = 200)` | 200 | `message` / `data` keys are omitted when arguments are null. |
| `respondCreated($data = null, $message = null)` | 201 | Falls back to the configured default message when `$message` is null. |
| `respondAccepted($data = null, $message = null)` | 202 | For async/queued operations. |
| `respondNoContent()` | 204 | Empty body. |
| `respondPaginated(LengthAwarePaginator $paginator, $message = null)` | 200 | Wraps a paginator into the `{data, meta, links}` envelope above. |

### Error
| Method | Status | Notes |
|---|---|---|
| `respondBadRequest($message = null, $errors = null)` | 400 | |
| `respondUnauthorized($message = null)` | 401 | |
| `respondForbidden($message = null)` | 403 | |
| `respondNotFound($message = null)` | 404 | |
| `respondMethodNotAllowed($message = null)` | 405 | |
| `respondConflict($message = null, $errors = null)` | 409 | |
| `respondUnprocessable(array $errors, $message = null)` | 422 | Same shape as Laravel's `ValidationException`. |
| `respondTooManyRequests($message = null, $retryAfter = null)` | 429 | Sets `Retry-After` header when provided. |
| `respondServerError($message = null)` | 500 | |
| `respondError($code, $message = null, $errors = null)` | any | For runtime-computed status codes (e.g. `418`). |

## Configuration

`config/boilerplate.php`:

```php
'responses' => [
    // When true, error helpers fill the message from default_messages below
    // when called with a null message. When false, the message key is omitted.
    'use_defaults' => true,

    'default_messages' => [
        'ok' => 'Success.',
        'created' => 'Resource created.',
        'accepted' => 'Request accepted.',
        'bad_request' => 'Bad request.',
        'unauthorized' => 'Unauthenticated.',
        'forbidden' => 'This action is unauthorized.',
        'not_found' => 'Resource not found.',
        'method_not_allowed' => 'Method not allowed.',
        'conflict' => 'Resource conflict.',
        'unprocessable' => 'The given data was invalid.',
        'too_many_requests' => 'Too many requests.',
        'server_error' => 'Server error.',
    ],
],

'exceptions' => [
    'render_for_api' => true,                                    // when false, Laravel's default rendering kicks in
    'expose_debug_in_response' => env('API_EXPOSE_DEBUG', false), // adds debug payload to 500 responses
],
```

## Localization

All default messages are passed through `__()`. The literal English string acts as the translation key — drop a `lang/<locale>/...` file with matching keys and Laravel will translate transparently. If you'd rather use namespaced keys (`messages.not_found`), put those keys directly into `default_messages` and add the matching translation file.

## Global Exception Handler

`App\Exceptions\ApiExceptionRenderer` is wired in `bootstrap/app.php`. It runs only for requests under `/api/*` or those expecting JSON; other requests fall through to Laravel's default renderer.

| Exception | Status | Notes |
|---|---|---|
| `AuthenticationException` | 401 | |
| `AuthorizationException` (becomes `AccessDeniedHttpException`) | 403 | |
| `ModelNotFoundException` (wrapped into `NotFoundHttpException`) | 404 | Defaults the message — never leaks `[App\Models\User] 99`. |
| `NotFoundHttpException` (route not found) | 404 | |
| `MethodNotAllowedHttpException` | 405 | |
| `ValidationException` | 422 | Returns `{message, errors}`. |
| `TooManyRequestsHttpException` / `ThrottleRequestsException` | 429 | Sets `Retry-After`. |
| Any other `HttpException` | code | Uses `getMessage()` when present, otherwise the configured default. |
| Any other `Throwable` | 500 | Generic message; debug block when `API_EXPOSE_DEBUG=true`. |

`abort(403, 'Custom reason.')` keeps the custom message; `abort(404)` falls back to the configured default.

## Usage

```php
use App\Http\Controllers\Controller;

class UsersController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return $this->respondNotFound();
        }
        return $this->respondOk($user);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        return $this->respondCreated(User::create($request->validated()));
    }

    public function index(): JsonResponse
    {
        return $this->respondPaginated(User::paginate());
    }

    public function destroy(int $id): JsonResponse
    {
        User::findOrFail($id)->delete();
        return $this->respondNoContent();
    }
}
```

## Key Files

| File | Purpose |
|---|---|
| `app/Http/Controllers/Controller.php` | Base controller with response helpers. |
| `app/Exceptions/ApiExceptionRenderer.php` | Global exception → envelope translator. |
| `bootstrap/app.php` | Registers the renderer. |
| `config/boilerplate.php` → `responses`, `exceptions` | All knobs. |
