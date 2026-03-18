<?php

namespace Xul\AuthKit\Support\Mappers\EmailVerification;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * VerifyEmailTokenPayloadMapper
 *
 * Default payload mapper for token-based email verification.
 *
 * Responsibilities:
 * - Translate validated token verification input into the normalized payload
 *   structure consumed by VerifyEmailTokenAction.
 * - Keep default field mapping centralized and overridable.
 */
class VerifyEmailTokenPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'email_verification_token';
    }

    /**
     * Return the default token verification field mapping definitions.
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
        ];
    }
}