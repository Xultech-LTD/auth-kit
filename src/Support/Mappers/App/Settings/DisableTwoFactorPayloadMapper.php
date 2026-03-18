<?php

namespace Xul\AuthKit\Support\Mappers\App\Settings;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * DisableTwoFactorPayloadMapper
 *
 * Default payload mapper for the authenticated disable-two-factor flow.
 *
 * Responsibilities:
 * - Describe how validated disable-two-factor input should be translated into
 *   the normalized payload consumed by the disable action.
 * - Keep disable-two-factor mapping behavior centralized and overridable.
 *
 * Default mapping behavior:
 * - code:
 *   Included in the attributes bucket and normalized with trim.
 * - recovery_code:
 *   Included in the attributes bucket and normalized with trim.
 *
 * Notes:
 * - The packaged mapper does not mark any fields as persistable by default.
 * - The action remains persistence-aware so consumers may extend this mapper
 *   and mark additional fields as persistable for auditing or analytics.
 * - Both fields may exist in the schema ecosystem, but runtime validation and
 *   action logic decide which credential path is actually used.
 */
class DisableTwoFactorPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'two_factor_disable';
    }

    /**
     * Return the default disable-two-factor mapping definitions.
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