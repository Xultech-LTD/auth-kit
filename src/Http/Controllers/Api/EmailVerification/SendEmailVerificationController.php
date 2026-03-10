<?php

namespace Xul\AuthKit\Http\Controllers\Api\EmailVerification;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\EmailVerification\SendEmailVerificationAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\DataTransferObjects\Actions\AuthKitActionResult;
use Xul\AuthKit\Http\Requests\EmailVerification\SendEmailVerificationRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * SendEmailVerificationController
 *
 * Resends an email verification message for the current user context.
 *
 * Responsibilities:
 * - Validate the incoming request through SendEmailVerificationRequest.
 * - Delegate resend orchestration to SendEmailVerificationAction.
 * - Return JSON responses for API or AJAX consumers.
 * - Return redirect responses with flash messages for standard web consumers.
 *
 * Security:
 * - Does not expose verification tokens or signed URLs.
 * - Delivery details are handled through AuthKitEmailVerificationRequired listeners.
 */
final class SendEmailVerificationController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * Handle the incoming request.
     *
     * @param SendEmailVerificationRequest $request
     * @param SendEmailVerificationAction $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        SendEmailVerificationRequest $request,
        SendEmailVerificationAction $action
    ): JsonResponse|RedirectResponse {
        $data = (array) $request->validated();

        $result = $action->handle((string) ($data['email'] ?? ''));

        if (ResponseResolver::expectsJson($request)) {
            return $this->ok($result->toArray(), $result->status);
        }

        return $this->toWebResponse($result, (string) ($data['email'] ?? ''));
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
        $noticeRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'verify_notice',
            'authkit.web.email.verify.notice'
        );

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
            routeName: $noticeRoute,
            parameters: $email !== '' ? ['email' => $email] : [],
            message: $result->message
        );
    }
}