<?php

namespace Xul\AuthKit\Http\Controllers\Web\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * LoginViewController
 *
 * Renders the login page.
 */
final class LoginViewController
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return View
     */
    public function __invoke(Request $request): View
    {
        return view('authkit::auth.login');
    }
}