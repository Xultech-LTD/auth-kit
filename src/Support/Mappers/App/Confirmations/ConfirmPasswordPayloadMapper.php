<?php

namespace Xul\AuthKit\Support\Mappers\App\Confirmations;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * ConfirmPasswordPayloadMapper
 *
 * Default payload mapper for AuthKit's authenticated password-confirmation flow.
 *
 * Responsibilities:
 * - Describe how validated confirm-password input should be translated into the
 *   normalized payload consumed by ConfirmPasswordAction.
 * - Keep password-confirmation mapping behavior centralized and overridable.
 *
 * Default mapping behavior:
 * - password:
 *   Included in the attributes bucket and normalized with trim.
 *
 * Notes:
 * - This mapper is used for step-up confirmation flows for already-authenticated users.
 * - The field is intentionally non-persistable by default.
 * - The confirmation action may still remain persistence-aware for consumers
 *   that later extend this flow through custom mappers.
 */
class ConfirmPasswordPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * @return string
     */
    public function context(): string
    {
        return 'confirm_password';
    }

    /**
     * Return the default confirm-password mapping definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'password' => [
                'source' => 'password',
                'target' => 'password',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => false,
                'transform' => 'trim',
            ],
        ];
    }
}