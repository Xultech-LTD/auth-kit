<?php

namespace Xul\AuthKit\Http\Controllers\Web\PasswordReset;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * ForgotPasswordSentViewController
 *
 * Renders the "reset link/token sent" confirmation page.
 *
 * Access:
 * - Protected by EnsurePendingPasswordResetMiddleware (password_reset_required stack).
 *
 * Required context:
 * - email via query string (?email=...)
 *
 * Notes:
 * - The page may provide a "resend" affordance.
 * - Sending/resending is handled by API routes.
 */
final class ForgotPasswordSentViewController
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return View
     */
    public function __invoke(Request $request): View
    {
        $email = mb_strtolower(trim((string) $request->query('email', '')));

        return view('authkit::password-reset.forgot-sent', [
            'driver' => (string) config('authkit.password_reset.driver', 'link'),
            'email' => $email,
            'status' => (string) session('status', session('message', '')),
            'error' => (string) session('error', ''),
        ]);
    }
}