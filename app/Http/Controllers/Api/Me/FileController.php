<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Requests\Me\ListFilesRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * @group Me
 */
class FileController extends Controller
{
    /**
     * List my files.
     *
     * Returns files owned by the authenticated user. By default only claimed
     * (persistent) files are listed — pass `?claimed=false` to see files
     * that still carry a TTL. Anonymously uploaded files are never listed
     * here regardless of filters.
     *
     * @authenticated
     *
     * @queryParam claimed boolean Filter by claim state. `true` = persistent (no TTL), `false` = pending. Defaults to `true`. Example: true
     * @queryParam visibility string Filter by visibility. `public` or `private`. Example: private
     * @queryParam q string Substring search on the original client filename. Example: invoice
     * @queryParam sort string One of `created_at`, `-created_at`, `size`, `-size`. Defaults to `-created_at`. Example: -created_at
     * @queryParam per_page integer 1–100. Defaults to 15. Example: 20
     * @queryParam page integer 1+. Example: 1
     *
     * @response 200 {"data": [{"id": "01HX...", "client_name": "photo.jpg"}], "meta": {"current_page": 1}, "links": {}}
     */
    public function index(ListFilesRequest $request): JsonResponse
    {
        $user = $this->currentUser($request);

        $query = File::query()->ownedBy($user);

        $this->applyClaimedFilter($query, $request->boolean('claimed', true));
        $this->applyVisibilityFilter($query, $request->string('visibility')->toString() ?: null);
        $this->applySearchFilter($query, $request->string('q')->toString() ?: null);
        $this->applySort($query, $request->string('sort', '-created_at')->toString());

        $paginator = $query
            ->paginate($this->resolvePerPage($request))
            ->withQueryString()
            ->through(fn (File $file): FileResource => new FileResource($file));

        return $this->respondPaginated($paginator);
    }

    /**
     * @param  Builder<File>  $query
     */
    private function applyClaimedFilter(Builder $query, bool $claimed): void
    {
        if ($claimed) {
            $query->whereNull('expires_at');
        } else {
            $query->whereNotNull('expires_at');
        }
    }

    /**
     * @param  Builder<File>  $query
     */
    private function applyVisibilityFilter(Builder $query, ?string $visibility): void
    {
        if ($visibility !== null) {
            $query->where('visibility', $visibility);
        }
    }

    /**
     * @param  Builder<File>  $query
     */
    private function applySearchFilter(Builder $query, ?string $term): void
    {
        if ($term !== null && $term !== '') {
            $query->where('client_name', 'like', '%'.$term.'%');
        }
    }

    /**
     * @param  Builder<File>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        $query->orderBy($column, $direction);
    }
}
