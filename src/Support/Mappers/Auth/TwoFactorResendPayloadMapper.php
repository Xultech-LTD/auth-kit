<?php

namespace Xul\AuthKit\Support\Mappers\Auth;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * TwoFactorResendPayloadMapper
 *
 * Default payload mapper for the two-factor resend flow.
 *
 * Responsibilities:
 * - Translate validated resend input into the normalized payload structure
 *   consumed by TwoFactorResendAction.
 * - Keep the default resend-field mapping centralized and overridable.
 *
 * Default mapping behavior:
 * - email:
 *   Included in the "attributes" bucket and exposed to the action as "email".
 *   A transform hint is provided so the payload builder may normalize it.
 *
 * Notes:
 * - This mapper only describes field definitions.
 * - It does not perform validation, delivery, or resend logic.
 * - Consumers may extend, merge, or replace this mapper through configuration.
 */
class TwoFactorResendPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'two_factor_resend';
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