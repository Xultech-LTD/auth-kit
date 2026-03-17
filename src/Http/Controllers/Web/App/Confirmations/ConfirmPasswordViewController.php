<?php

namespace Xul\AuthKit\Http\Controllers\Web\App\Confirmations;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * ConfirmPasswordViewController
 *
 * Renders the authenticated password-confirmation page used for step-up
 * security flows.
 *
 * Responsibilities:
 * - Resolve the configured confirmation page definition from AuthKit config.
 * - Resolve title, heading, view, and JavaScript page key metadata.
 * - Render the configured view with minimal page context.
 *
 * Notes:
 * - This page is intended for already-authenticated users only.
 * - The actual password verification is handled by the paired API action,
 *   not by this controller.
 * - This page is typically reached when protected middleware detects that
 *   no fresh password confirmation exists in session.
 */
final class ConfirmPasswordViewController
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return View
     */
    public function __invoke(Request $request): View
    {
        $page = (array) data_get(config('authkit.app', []), 'pages.confirm_password', []);

        $title = (string) ($page['title'] ?? 'Confirm password');
        $heading = (string) ($page['heading'] ?? 'Confirm your password');
        $view = (string) ($page['view'] ?? 'authkit::pages.app.confirm-password');
        $pageKey = (string) data_get(
            config('authkit.javascript.pages', []),
            'confirm_password.page_key',
            'confirm_password'
        );

        return view($view, [
            'title' => $title,
            'heading' => $heading,
            'pageKey' => $pageKey,
            'currentPage' => 'confirm_password',
        ]);
    }
}