<?php

namespace Xul\AuthKit\Http\Controllers\Web\App;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Xul\AuthKit\Support\App\SessionViewDataResolver;

/**
 * SessionsViewController
 *
 * Renders the authenticated sessions page.
 *
 * Responsibilities:
 * - Resolve the configured sessions page definition.
 * - Delegate session view-data resolution.
 * - Render the configured sessions view.
 *
 * Notes:
 * - This controller intentionally remains thin.
 * - Session querying and normalization are delegated to dedicated support
 *   classes so the controller stays focused on HTTP orchestration.
 */
final class SessionsViewController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, SessionViewDataResolver $resolver): View
    {
        $page = (array) data_get(config('authkit.app', []), 'pages.sessions', []);

        $title = (string) ($page['title'] ?? 'Sessions');
        $heading = (string) ($page['heading'] ?? 'Manage active sessions');
        $view = (string) ($page['view'] ?? 'authkit::pages.app.sessions');

        return view($view, [
            'title' => $title,
            'heading' => $heading,
            'pageKey' => 'sessions',
            'currentPage' => 'sessions',
            ...$resolver->resolve($request),
        ]);
    }
}