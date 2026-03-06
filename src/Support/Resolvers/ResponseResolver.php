<?php

namespace Xul\AuthKit\Support\Resolvers;

use Illuminate\Http\Request;

final class ResponseResolver
{
    /**
     * Determine whether the current request should receive a JSON response.
     *
     * Resolution order:
     * 1) If AuthKit forms mode is configured as "ajax", always respond with JSON.
     * 2) If the request explicitly expects JSON, respond with JSON.
     * 3) Otherwise, treat as a standard browser request (redirect responses expected).
     *
     * Notes:
     * - Blade "data-authkit-ajax" is a client-side marker and is not reliably available server-side.
     * - For fetch/XHR flows, ensure the client sets "Accept: application/json" .
     */
    public static function expectsJson(Request $request): bool
    {
        $mode = (string) config('authkit.forms.mode', 'http');

        if ($mode === 'ajax') {
            return true;
        }

        return $request->expectsJson() || $request->wantsJson() || $request->ajax();
    }
}