<?php

namespace Xul\AuthKit\Support\Mappers\Auth;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * TwoFactorChallengePayloadMapper
 *
 * Default payload mapper for the login-time two-factor challenge flow.
 *
 * Responsibilities:
 * - Translate validated two-factor challenge input into the normalized
 *   payload consumed by the action layer.
 * - Keep the package default mapping centralized and overridable.
 *
 * Default mapping behavior:
 * - challenge:
 *   Included in the attributes bucket and normalized with trim.
 * - code:
 *   Included in the attributes bucket and normalized with trim.
 *
 * Notes:
 * - This mapper is for login-time two-factor challenge completion.
 * - This is distinct from authenticated step-up confirmation flows.
 */
class TwoFactorChallengePayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'two_factor_challenge';
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

            'code' => [
                'source' => 'code',
                'target' => 'code',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => false,
                'transform' => 'trim',
            ],
        ];
    }
}