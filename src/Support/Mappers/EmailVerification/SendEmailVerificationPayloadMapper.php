<?php

namespace Xul\AuthKit\Support\Mappers\EmailVerification;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * SendEmailVerificationPayloadMapper
 *
 * Default payload mapper for the email verification resend flow.
 *
 * Responsibilities:
 * - Translate validated resend input into the normalized payload structure
 *   consumed by SendEmailVerificationAction.
 * - Keep the default resend field mapping centralized and overridable.
 *
 * Default mapping behavior:
 * - email:
 *   Included in the "attributes" bucket and exposed to the action as "email".
 *   A transform hint is provided so the payload builder may normalize it.
 *
 * Notes:
 * - This mapper only describes field definitions.
 * - It does not perform validation, token generation, or delivery.
 * - Consumers may extend, merge, or replace this mapper through configuration.
 */
class SendEmailVerificationPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'email_verification_send';
    }

    /**
     * Return the default resend field mapping definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'email' => [
                'source' => 'email',
                'target' => 'email',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => false,
                'transform' => 'lower_trim',
            ],
        ];
    }
}