<?php

namespace Xul\AuthKit\Http\Controllers\Web\App\Confirmations;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * ConfirmTwoFactorViewController
 *
 * Renders the authenticated two-factor confirmation page used for
 * step-up security checks before allowing access to sensitive pages
 * or actions.
 *
 * Responsibilities:
 * - Resolve the configured confirm-two-factor page definition.
 * - Pass page metadata into the configured view.
 * - Keep rendering lightweight and configuration-driven.
 *
 * Notes:
 * - This page is distinct from the login-time two-factor challenge flow.
 * - It is used only after the user is already authenticated.
 * - The actual confirmation submission is handled by the corresponding
 *   API/action controller.
 */
final class ConfirmTwoFactorViewController
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return View
     */
    public function __invoke(Request $request): View
    {
        $page = (array) data_get(config('authkit.app', []), 'pages.confirm_two_factor', []);

        $title = (string) ($page['title'] ?? 'Confirm two-factor authentication');
        $heading = (string) ($page['heading'] ?? 'Confirm two-factor authentication');
        $view = (string) ($page['view'] ?? 'authkit::pages.app.confirm-two-factor');

        return view($view, [
            'title' => $title,
            'heading' => $heading,
            'pageKey' => 'confirm_two_factor',
            'currentPage' => 'confirm_two_factor',
        ]);
    }
}