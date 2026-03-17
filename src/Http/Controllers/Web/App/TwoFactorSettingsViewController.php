<?php

namespace Xul\AuthKit\Http\Controllers\Web\App;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Xul\AuthKit\Support\App\ResolveTwoFactorSettingsPageData;

/**
 * TwoFactorSettingsViewController
 *
 * Renders the authenticated two-factor management page.
 *
 * Responsibilities:
 * - Resolve the configured two-factor settings page definition.
 * - Resolve the authenticated user.
 * - Delegate page-specific display data to a dedicated resolver.
 * - Render the configured view.
 */
final class TwoFactorSettingsViewController
{
    public function __construct(
        protected ResolveTwoFactorSettingsPageData $resolver
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $page = (array) data_get(config('authkit.app', []), 'pages.two_factor_settings', []);

        $title = (string) ($page['title'] ?? 'Two-factor authentication');
        $heading = (string) ($page['heading'] ?? 'Manage two-factor authentication');
        $view = (string) ($page['view'] ?? 'authkit::pages.app.two-factor');

        $guard = (string) config('authkit.auth.guard', 'web');
        $user = auth($guard)->user();

        return view($view, [
            'title' => $title,
            'heading' => $heading,
            'pageKey' => 'two_factor_settings',
            'currentPage' => 'two_factor_settings',
            ...$this->resolver->resolve($user),
        ]);
    }
}