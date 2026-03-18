<?php

namespace Xul\AuthKit\Support\Mappers\App\Settings;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * ConfirmTwoFactorSetupPayloadMapper
 *
 * Default payload mapper for the authenticated two-factor setup confirmation flow.
 *
 * Responsibilities:
 * - Describe how validated setup-confirmation input should be translated into
 *   the normalized payload consumed by ConfirmTwoFactorSetupAction.
 * - Keep mapping behavior centralized and overridable.
 *
 * Default mapping behavior:
 * - code:
 *   Included in the attributes bucket and normalized with trim.
 *
 * Notes:
 * - The packaged mapping intentionally keeps the submitted confirmation code
 *   non-persistable.
 * - The action remains persistence-aware so consumers may extend this mapper
 *   and mark additional attributes as persistable when needed.
 */
final class ConfirmTwoFactorSetupPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'two_factor_confirm';
    }

    /**
     * Return the default mapping definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
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