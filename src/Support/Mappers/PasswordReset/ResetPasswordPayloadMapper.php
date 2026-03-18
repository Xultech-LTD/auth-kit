<?php

namespace Xul\AuthKit\Support\Mappers\PasswordReset;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * ResetPasswordPayloadMapper
 *
 * Default payload mapper for the AuthKit reset-password flow.
 *
 * Responsibilities:
 * - Describe how validated reset-password input should be translated into the
 *   normalized payload consumed by the reset password action.
 * - Keep reset-password mapping behavior centralized and overridable.
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
 * - This mapper supports both link-driven and token-driven reset flows.
 * - The action only needs the final reset credential token plus the new password.
 */
class ResetPasswordPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'password_reset';
    }

    /**
     * Return the default reset-password mapping definitions.
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