<?php

namespace Xul\AuthKit\Support\Mappers\Auth;

use Xul\AuthKit\Support\Mappers\AbstractPayloadMapper;

/**
 * RegisterPayloadMapper
 *
 * Default payload mapper for the AuthKit registration flow.
 *
 * Responsibilities:
 * - Describe how validated registration input should be translated into
 *   the normalized payload consumed by the registration action.
 * - Define the package-default mapping for built-in registration fields.
 * - Keep registration field-to-payload rules centralized and overridable.
 *
 * Default mapping behavior:
 * - name:
 *   Included in the "attributes" bucket, persisted as "name",
 *   and trimmed before being placed into the payload.
 * - email:
 *   Included in the "attributes" bucket, persisted as "email",
 *   and normalized using the lower_trim transform.
 * - password:
 *   Included in the "attributes" bucket, persisted as "password",
 *   and hashed before being placed into the payload.
 * - password_confirmation:
 *   Excluded from the mapped payload by default because it is a
 *   validation-only field and is not intended for persistence.
 *
 * Notes:
 * - This mapper only defines mapping metadata.
 * - It does not validate input.
 * - It does not persist data.
 * - It does not execute transforms directly; that responsibility belongs
 *   to the mapped payload builder.
 * - Consumers may replace or extend this mapper through AuthKit config.
 */
class RegisterPayloadMapper extends AbstractPayloadMapper
{
    /**
     * Return the logical mapper context.
     *
     * This context key aligns with:
     * - authkit.mappers.contexts.register
     * - authkit.schemas.register
     * - authkit.validation.providers.register
     *
     * @return string
     */
    public function context(): string
    {
        return 'register';
    }

    /**
     * Return the default registration mapping definitions.
     *
     * Definition keys:
     * - source:
     *   Source field key from the validated request payload.
     * - target:
     *   Destination key inside the mapped payload bucket.
     * - bucket:
     *   Top-level payload bucket. Common examples are:
     *   - attributes
     *   - options
     *   - meta
     * - include:
     *   Whether the field should be emitted into the mapped payload.
     * - persist:
     *   Whether the field is intended for persistence/business handling.
     * - transform:
     *   Built-in transform hint consumed by the payload builder.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'name' => [
                'source' => 'name',
                'target' => 'name',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'trim',
            ],

            'email' => [
                'source' => 'email',
                'target' => 'email',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'lower_trim',
            ],

            'password' => [
                'source' => 'password',
                'target' => 'password',
                'bucket' => 'attributes',
                'include' => true,
                'persist' => true,
                'transform' => 'hash',
            ],

            'password_confirmation' => [
                'source' => 'password_confirmation',
                'include' => false,
                'persist' => false,
            ],
        ];
    }
}