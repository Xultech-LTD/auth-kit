<?php

namespace Xul\AuthKit\Support\Mappers\Auth;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * TwoFactorRecoveryPayloadMapper
 *
 * Default payload mapper for two-factor recovery challenge completion.
 *
 * Responsibilities:
 * - Map the challenge identifier into the normalized attributes payload.
 * - Map the submitted recovery code into the normalized attributes payload.
 * - Keep recovery payload behavior centralized and overridable.
 */
class TwoFactorRecoveryPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'two_factor_recovery';
    }

    /**
     * Return the default mapping definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'challenge' => [
                'source' => 'challenge',
                'target' => 'challenge',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => false,
                'transform' => 'trim',
            ],

            'recovery_code' => [
                'source' => 'recovery_code',
                'target' => 'recovery_code',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => false,
                'transform' => 'trim',
            ],
        ];
    }
}