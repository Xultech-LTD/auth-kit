<?php

namespace Xul\AuthKit\Support\Mappers\PasswordReset;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * VerifyPasswordResetTokenPayloadMapper
 *
 * Default payload mapper for the AuthKit password-reset token verification flow.
 *
 * Responsibilities:
 * - Describe how validated password-reset token verification input should be
 *   translated into the normalized payload consumed by the action.
 * - Keep mapping behavior centralized and overridable.
 *
 * Default mapping behavior:
 * - email:
 *   Included in the attributes bucket and normalized with lower_trim.
 * - token:
 *   Included in the attributes bucket and normalized with trim.
 * - password:
 *   Included in the attributes bucket unchanged.
 * - password_confirmation:
 *   Included in the attributes bucket unchanged.
 *
 * Notes:
 * - This mapper is intended for token-driver password-reset flows where the
 *   token is manually submitted by the user.
 */
class VerifyPasswordResetTokenPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'password_reset_token';
    }

    /**
     * Return the default mapping definitions.
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

            'token' => [
                'source' => 'token',
                'target' => 'token',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => false,
                'transform' => 'trim',
            ],

            'password' => [
                'source' => 'password',
                'target' => 'password',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => false,
            ],

            'password_confirmation' => [
                'source' => 'password_confirmation',
                'target' => 'password_confirmation',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => false,
            ],
        ];
    }
}