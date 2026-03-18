<?php

namespace Xul\AuthKit\Support\Mappers\PasswordReset;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * ForgotPasswordPayloadMapper
 *
 * Default payload mapper for the AuthKit forgot-password flow.
 *
 * Responsibilities:
 * - Describe how validated forgot-password input should be translated into the
 *   normalized payload consumed by the password reset request action.
 * - Keep forgot-password mapping behavior centralized and overridable.
 *
 * Default mapping behavior:
 * - email:
 *   Included in the attributes bucket and normalized with lower_trim.
 *
 * Notes:
 * - The forgot-password flow currently uses email as the identity value.
 * - Consumers may override this mapper when using username, phone,
 *   or another reset identity strategy.
 */
class ForgotPasswordPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'password_forgot';
    }

    /**
     * Return the default forgot-password mapping definitions.
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