<?php

namespace Xul\AuthKit\Http\Controllers\Api\EmailVerification;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\EmailVerification\VerifyEmailTokenAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\EmailVerification\EmailVerificationTokenRequest;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * VerifyEmailTokenController
 *
 * Verifies an email address using a token or code.
 *
 * Responsibilities:
 * - Validate the incoming request through EmailVerificationTokenRequest.
 * - Build the normalized mapped payload for the token verification context.
 * - Delegate token verification orchestration to VerifyEmailTokenAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 */
final class VerifyEmailTokenController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Verify email using a token or code.
     *
     * @param EmailVerificationTokenRequest $request
     * @param VerifyEmailTokenAction $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        EmailVerificationTokenRequest $request,
        VerifyEmailTokenAction $action
    ): JsonResponse|RedirectResponse {
        $payload = MappedPayloadBuilder::build('email_verification_token', $request->validated());

        $result = $action->handle($payload);

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($result->toArray(), $result->status);
        }

        return $this->toWebResponse($result, (string) data_get($payload, 'attributes.email', ''));
    }

    /**
     * Convert the standardized action result into a web redirect response.
     *
     * @param AuthKitActionResult $result
     * @param string $email
     * @return RedirectResponse
     */
    protected function toWebResponse(AuthKitActionResult $result, string $email): RedirectResponse
    {
        $webNames = (array) config('authkit.route_names.web', []);
        $noticeRoute = (string) ($webNames['verify_notice'] ?? 'authkit.web.email.verify.notice');
        $loginRoute = (string) ($webNames['login'] ?? 'authkit.web.login');

        if (! $result->ok) {
            if ($result->hasRedirect() && $result->redirect?->isRoute()) {
                return $this->toRouteWithError(
                    routeName: $result->redirect->target,
                    parameters: $result->redirect->parameters,
                    message: $result->message
                );
            }

            return $this->toRouteWithError(
                routeName: $noticeRoute,
                parameters: $email !== '' ? ['email' => $email] : [],
                message: $result->message
            );
        }

        if ($result->hasRedirect() && $result->redirect?->isRoute()) {
            return $this->toRouteWithStatus(
                routeName: $result->redirect->target,
                parameters: $result->redirect->parameters,
                message: $result->message
            );
        }

        return $this->toRouteWithStatus(
            routeName: $loginRoute,
            parameters: [],
            message: $result->message
        );
    }
}