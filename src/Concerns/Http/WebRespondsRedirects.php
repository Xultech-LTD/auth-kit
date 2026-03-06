<?php

namespace Xul\AuthKit\Concerns\Http;

use Illuminate\Http\RedirectResponse;

trait WebRespondsRedirects
{
    /**
     * Redirect back with a flash status message.
     *
     * Used by SSR (non-AJAX) form flows.
     */
    protected function backWithStatus(string $message, string $key = 'status'): RedirectResponse
    {
        return redirect()
            ->back()
            ->with($key, $message);
    }

    /**
     * Redirect back with a flash error message.
     *
     * Used by SSR (non-AJAX) form flows.
     */
    protected function backWithError(string $message, string $key = 'error'): RedirectResponse
    {
        return redirect()
            ->back()
            ->with($key, $message);
    }

    /**
     * Redirect to a named route with an optional flash status message.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function toRouteWithStatus(
        string $routeName,
        array $parameters = [],
        ?string $message = null,
        string $key = 'status'
    ): RedirectResponse {
        $redirect = redirect()->route($routeName, $parameters);

        if (is_string($message) && $message !== '') {
            $redirect->with($key, $message);
        }

        return $redirect;
    }

    /**
     * Redirect to a named route with an optional flash error message.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function toRouteWithError(
        string $routeName,
        array $parameters = [],
        ?string $message = null,
        string $key = 'error'
    ): RedirectResponse {
        $redirect = redirect()->route($routeName, $parameters);

        if (is_string($message) && $message !== '') {
            $redirect->with($key, $message);
        }

        return $redirect;
    }
}