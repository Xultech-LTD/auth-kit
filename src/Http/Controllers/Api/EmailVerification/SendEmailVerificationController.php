<?php

namespace Xul\AuthKit\Http\Controllers\Api\EmailVerification;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Xul\AuthKit\Actions\EmailVerification\SendEmailVerificationAction;
use Xul\AuthKit\Concerns\Http\ApiRespondsJson;
use Xul\AuthKit\Concerns\Http\WebRespondsRedirects;
use Xul\AuthKit\Http\Requests\EmailVerification\SendEmailVerificationRequest;
use Xul\AuthKit\Support\Resolvers\ResponseResolver;

/**
 * SendEmailVerificationController
 *
 * Resends email verification message for the current user context.
 *
 * Returns:
 * - JSON responses for AJAX/JSON requests.
 * - Redirect responses with flash messages for standard SSR form posts.
 *
 * Security:
 * - Does not expose verification tokens or signed URLs.
 * - Delivery details are handled via AuthKitEmailVerificationRequired listeners.
 */
final class SendEmailVerificationController
{
    use ApiRespondsJson;
    use WebRespondsRedirects;

    /**
     * @param SendEmailVerificationRequest $request
     * @param SendEmailVerificationAction $action
     * @return JsonResponse|RedirectResponse
     */
    public function __invoke(
        SendEmailVerificationRequest $request,
        SendEmailVerificationAction $action
    ): JsonResponse|RedirectResponse {
        $data = (array) $request->validated();

        $result = $action->execute((string) ($data['email'] ?? ''));

        if (ResponseResolver::expectsJson($request)) {
            if (! $result->ok) {
                return $this->ok([
                    'ok' => false,
                    'message' => $result->message,
                ], 422);
            }

            return $this->ok([
                'ok' => true,
                'message' => $result->message,
                'driver' => $result->driver,
            ], 200);
        }

        $noticeRoute = (string) data_get(
            config('authkit.route_names.web', []),
            'verify_notice',
            'authkit.web.email.verify.notice'
        );

        if (! $result->ok) {
            return $this->toRouteWithError(
                routeName: $noticeRoute,
                parameters: ['email' => (string) ($data['email'] ?? '')],
                message: $result->message
            );
        }

        return $this->toRouteWithStatus(
            routeName: $noticeRoute,
            parameters: ['email' => (string) ($data['email'] ?? '')],
            message: $result->message
        );
    }
}