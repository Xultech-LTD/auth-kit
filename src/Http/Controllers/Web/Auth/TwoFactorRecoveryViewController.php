<?php

namespace Xul\AuthKit\Http\Controllers\Web\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Psr\SimpleCache\InvalidArgumentException;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\PendingLogin;

/**
 * TwoFactorRecoveryViewController
 *
 * Renders the recovery-code page for a pending login challenge.
 *
 * The page requires a pending login challenge reference (query param "c").
 * The pending challenge is verified using a non-consuming lookup to ensure
 * the challenge exists and has not expired.
 */
final class TwoFactorRecoveryViewController
{
    /**
     * Create a new instance.
     *
     * @param PendingLogin $pending
     */
    public function __construct(
        protected PendingLogin $pending
    ) {}

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return View|RedirectResponse
     * @throws InvalidArgumentException
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        $challenge = (string) $request->query('c', '');

        if ($challenge === '') {
            $challenge = (string) $request->session()->get(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, '');
        }

        if ($challenge === '') {
            return redirect()
                ->route((string) data_get(config('authkit.route_names.web', []), 'login', 'authkit.web.login'))
                ->with('error', 'Missing two-factor challenge.');
        }

        $payload = $this->pending->peek($challenge);

        if (! $payload) {
            $request->session()->forget(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE);

            return redirect()
                ->route((string) data_get(config('authkit.route_names.web', []), 'login', 'authkit.web.login'))
                ->with('error', 'Expired or invalid two-factor challenge.');
        }

        $request->session()->put(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, $challenge);

        return view('authkit::auth.two-factor-recovery', [
            'methods' => (array) data_get($payload, 'methods', ['totp']),
        ]);
    }
}