<?php

namespace Xul\AuthKit\Support\Mappers\App\Settings;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * RegenerateTwoFactorRecoveryCodesPayloadMapper
 *
 * Default payload mapper for the authenticated two-factor recovery-code
 * regeneration flow.
 *
 * Responsibilities:
 * - Describe how validated recovery-regeneration input should be translated
 *   into the normalized payload consumed by the action.
 * - Keep recovery-regeneration mapping behavior centralized and overridable.
 *
 * Default mapping behavior:
 * - code:
 *   Included in the attributes bucket and normalized with trim.
 *
 * Notes:
 * - The packaged mapper does not mark fields as persistable by default.
 * - The action remains persistence-aware so consumers may extend this mapper
 *   and mark additional fields as persistable for auditing or analytics.
 */
class RegenerateTwoFactorRecoveryCodesPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'two_factor_recovery_regenerate';
    }

    /**
     * Return the default recovery-regeneration mapping definitions.
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