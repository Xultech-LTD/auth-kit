<?php

namespace Xul\AuthKit\Http\Controllers\Web\PasswordReset;

use Illuminate\Contracts\View\View;

/**
 * ResetPasswordSuccessViewController
 *
 * Renders the success page after a password reset.
 *
 * Notes:
 * - This is a UX page; the actual reset occurs via API/action endpoint.
 */
final class ResetPasswordSuccessViewController
{
    /**
     * Handle the incoming request.
     *
     * @return View
     */
    public function __invoke(): View
    {
        return view('authkit::password-reset.success');
    }
}