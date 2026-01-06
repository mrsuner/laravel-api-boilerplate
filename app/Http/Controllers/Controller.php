<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Return a success response.
     */
    protected function respondOk(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = [];

        if (! is_null($message)) {
            $response['message'] = $message;
        }

        if (! is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }
}
