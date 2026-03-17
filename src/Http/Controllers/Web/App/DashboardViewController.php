<?php

namespace Xul\AuthKit\Http\Controllers\Web\App;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * DashboardViewController
 *
 * Renders the authenticated dashboard page.
 *
 * Responsibilities:
 * - Resolve the configured dashboard page definition.
 * - Render the configured dashboard view.
 *
 * Notes:
 * - This controller intentionally remains thin.
 * - Dashboard-specific presentation and lightweight display concerns
 *   should live in the Blade view.
 */
final class DashboardViewController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $page = (array) data_get(config('authkit.app', []), 'pages.dashboard_web', []);

        $title = (string) ($page['title'] ?? 'Dashboard');
        $heading = (string) ($page['heading'] ?? 'Account overview');
        $view = (string) ($page['view'] ?? 'authkit::pages.app.dashboard');

        return view($view, [
            'title' => $title,
            'heading' => $heading,
            'pageKey' => 'dashboard_web',
            'currentPage' => 'dashboard_web',
        ]);
    }
}