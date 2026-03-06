<?php

namespace Xul\AuthKit\Http\Controllers\Web\EmailVerification;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * EmailVerificationNoticeViewController
 *
 * Renders the email verification notice page.
 *
 * This page is intentionally stateless: it does not assume a logged-in user.
 * It relies on an email context (typically passed as a query string) and is
 * protected by EnsurePendingEmailVerificationMiddleware.
 */
final class EmailVerificationNoticeViewController
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

        return view('authkit::email-verification.notice', [
            'email' => $email,
            'driver' => (string) config('authkit.email_verification.driver', 'link'),
        ]);
    }
}