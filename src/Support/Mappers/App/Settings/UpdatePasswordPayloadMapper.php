<?php

namespace Xul\AuthKit\Support\Mappers\App\Settings;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * UpdatePasswordPayloadMapper
 *
 * Default payload mapper for AuthKit's authenticated password-update flow.
 *
 * Responsibilities:
 * - Describe how validated password-update input should be translated into the
 *   normalized payload consumed by UpdatePasswordAction.
 * - Keep password-update mapping behavior centralized and overridable.
 *
 * Default mapping behavior:
 * - current_password:
 *   Included in the attributes bucket and normalized with trim.
 * - password:
 *   Included in the attributes bucket unchanged.
 * - password_confirmation:
 *   Included in the attributes bucket unchanged.
 * - logout_other_devices:
 *   Included in the options bucket and normalized to boolean.
 *
 * Notes:
 * - Default password-update fields are intentionally non-persistable.
 * - The action remains persistence-aware so consumers may extend this mapper
 *   and mark selected fields as persistable when their model supports
 *   AuthKit mapped persistence.
 */
class UpdatePasswordPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'password_update';
    }

    /**
     * Return the default password-update mapping definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'current_password' => [
                'source' => 'current_password',
                'target' => 'current_password',
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

            'logout_other_devices' => [
                'source' => 'logout_other_devices',
                'target' => 'logout_other_devices',
                'bucket' => 'options',
                'include' => true,
                'persist' => false,
                'transform' => 'boolean',
            ],
        ];
    }
}