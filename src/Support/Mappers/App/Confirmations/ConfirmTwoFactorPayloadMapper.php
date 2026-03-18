<?php

namespace Xul\AuthKit\Support\Mappers\App\Confirmations;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * ConfirmTwoFactorPayloadMapper
 *
 * Default payload mapper for the AuthKit authenticated two-factor confirmation flow.
 *
 * Responsibilities:
 * - Describe how validated confirm-two-factor input should be translated into the
 *   normalized payload consumed by the confirmation action.
 * - Keep confirm-two-factor mapping behavior centralized and overridable.
 *
 * Default mapping behavior:
 * - code:
 *   Included in the attributes bucket and normalized with trim.
 *
 * Notes:
 * - Default confirmation fields are intentionally non-persistable, but the
 *   action remains persistence-aware for future customization.
 * - Consumers may extend or replace this mapper if they want to persist
 *   additional confirmation metadata onto the authenticated user model.
 */
class ConfirmTwoFactorPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'confirm_two_factor';
    }

    /**
     * Return the default confirm-two-factor mapping definitions.
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