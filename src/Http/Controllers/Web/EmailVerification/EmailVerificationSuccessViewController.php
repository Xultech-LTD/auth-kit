<?php

namespace Xul\AuthKit\Http\Controllers\Web\EmailVerification;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * EmailVerificationSuccessViewController
 *
 * Renders a post-verification success page.
 *
 * This page is optional and enabled via email_verification.post_verify.mode=success_page.
 */
final class EmailVerificationSuccessViewController
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return View
     */
    public function __invoke(Request $request): View
    {
        $status = (string) session('status', 'Your email has been verified.');

        return view('authkit::email-verification.success', [
            'status' => $status,
        ]);
    }
}