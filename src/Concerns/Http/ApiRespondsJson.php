<?php

namespace Xul\AuthKit\Concerns\Http;

use Illuminate\Http\JsonResponse;

trait ApiRespondsJson
{
    /**
     * Return a standardized JSON success response.
     *
     * Intended for API/action controllers that operate in JSON mode
     * (including AJAX form submissions).
     *
     * @param  array<string, mixed>  $data
     */
    protected function ok(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    /**
     * Return a standardized JSON error response.
     *
     * Intended for API/action controllers when a request fails
     * business logic validation (not FormRequest validation).
     *
     * @param  array<string, mixed>  $data
     */
    protected function fail(array $data = [], int $status = 422): JsonResponse
    {
        return response()->json($data, $status);
    }
}