<?php

namespace Xul\AuthKit\Http\Controllers\Web\App;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * SettingsViewController
 *
 * Renders the authenticated settings overview page.
 *
 * Responsibilities:
 * - Resolve the configured settings page definition.
 * - Resolve related authenticated app page definitions for linked sections.
 * - Render the configured settings overview view.
 *
 * Notes:
 * - This controller intentionally remains thin.
 * - It prepares lightweight page metadata only.
 * - Detailed section rendering belongs to the Blade view and reusable
 *   authenticated app components.
 */
final class SettingsViewController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $app = (array) config('authkit.app', []);
        $pages = (array) data_get($app, 'pages', []);

        $page = (array) ($pages['settings'] ?? []);

        $title = (string) ($page['title'] ?? 'Settings');
        $heading = (string) ($page['heading'] ?? 'Account settings');
        $view = (string) ($page['view'] ?? 'authkit::pages.app.settings');

        return view($view, [
            'title' => $title,
            'heading' => $heading,
            'pageKey' => 'settings',
            'currentPage' => 'settings',
            'pages' => $pages,
        ]);
    }
}