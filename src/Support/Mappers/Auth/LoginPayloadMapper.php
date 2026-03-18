<?php

namespace Xul\AuthKit\Support\Mappers\Auth;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * LoginPayloadMapper
 *
 * Default payload mapper for the AuthKit login flow.
 *
 * Responsibilities:
 * - Describe how validated login input should be translated into the
 *   normalized payload consumed by the login action.
 * - Keep login mapping behavior centralized and overridable.
 *
 * Default mapping behavior:
 * - email:
 *   Included in the attributes bucket and normalized with lower_trim.
 * - password:
 *   Included in the attributes bucket unchanged.
 * - remember:
 *   Included in the options bucket and normalized to boolean.
 *
 * Notes:
 * - The identity field defaults to email in the package schema.
 * - Consumers may override this mapper when using username, phone,
 *   or other login identities.
 * - Default login fields are intentionally non-persistable, but the
 *   login action remains persistence-aware for future customization.
 */
class LoginPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'login';
    }

    /**
     * Return the default login mapping definitions.
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

            'password' => [
                'source' => 'password',
                'target' => 'password',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => false,
            ],

            'remember' => [
                'source' => 'remember',
                'target' => 'remember',
                'bucket' => 'options',
                'include' => true,
                'persist' => false,
                'transform' => 'boolean',
            ],
        ];
    }
}