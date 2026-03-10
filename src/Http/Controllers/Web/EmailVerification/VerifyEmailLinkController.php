<?php

namespace Xul\AuthKit\Http\Controllers\Web\EmailVerification;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Xul\AuthKit\Actions\EmailVerification\VerifyEmailLinkAction;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;

/**
 * VerifyEmailLinkController
 *
 * Handles signed email verification link requests.
 *
 * This controller delegates verification logic to VerifyEmailLinkAction and
 * applies redirect behavior from the standardized action result.
 */
final class VerifyEmailLinkController
{
    /**
     * Create a new instance.
     *
     * @param VerifyEmailLinkAction $action
     */
    public function __construct(
        protected VerifyEmailLinkAction $action
    ) {}

    /**
     * Handle the incoming request.
     *
     * Route params:
     * - id: user identifier
     * - hash: raw verification token
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $id = (string) $request->route('id', '');
        $hash = (string) $request->route('hash', '');

        $result = $this->action->handle($id, $hash);

        return $this->toWebResponse($result);
    }

    /**
     * Convert the standardized action result into a redirect response.
     *
     * @param AuthKitActionResult $result
     * @return RedirectResponse
     */
    protected function toWebResponse(AuthKitActionResult $result): RedirectResponse
    {
        $redirect = $result->redirect;

        if ($redirect !== null && $redirect->isRoute()) {
            return $result->ok
                ? redirect()
                    ->route($redirect->target, $redirect->parameters)
                    ->with('status', $result->message)
                : redirect()
                    ->route($redirect->target, $redirect->parameters)
                    ->with('error', $result->message);
        }

        $login = (string) data_get(
            config('authkit.route_names.web', []),
            'login',
            'authkit.web.login'
        );

        return $result->ok
            ? redirect()->route($login)->with('status', $result->message)
            : redirect()->route($login)->with('error', $result->message);
    }
}