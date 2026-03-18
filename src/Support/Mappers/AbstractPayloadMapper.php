<?php

namespace Xul\AuthKit\Support\Mappers;

use Xul\AuthKit\Contracts\Mappers\PayloadMapperContract;

/**
 * AbstractPayloadMapper
 *
 * Lightweight base implementation for AuthKit payload mappers.
 *
 * Purpose:
 * - Reduce repeated boilerplate across package and consumer mapper classes.
 * - Provide sensible defaults for the most common mapper behavior.
 * - Keep custom mapper implementations focused on their field definitions.
 *
 * Default behavior:
 * - schema(): Uses the same value as context().
 * - mode(): Uses merge mode by default so custom mappers can extend
 *   package defaults unless they explicitly opt into replacement.
 * - remove(): Returns an empty list by default.
 *
 * Design notes:
 * - Concrete mappers are still required to implement:
 *   - context()
 *   - definitions()
 * - Consumers may override any inherited method where needed.
 * - This class does not execute payload mapping; it only provides
 *   baseline metadata for mapper definitions.
 */
abstract class AbstractPayloadMapper implements PayloadMapperContract
{
    /**
     * Return the schema context used by this mapper.
     *
     * Default behavior:
     * - Use the same key returned by context().
     *
     * This keeps the common case simple, since most AuthKit mapper
     * contexts map directly to a schema with the same name.
     *
     * Consumers may override this when a mapper should intentionally
     * reference a different schema context or when a context has no
     * visible schema at all.
     *
     * @return string|null
     */
    public function schema(): ?string
    {
        return $this->context();
    }

    /**
     * Return the mapper mode.
     *
     * Default behavior:
     * - Merge mode.
     *
     * Rationale:
     * - This makes it easy for consumers to extend package defaults
     *   by adding or adjusting only the field definitions they care about.
     * - Full replacement remains available by overriding this method
     *   and returning PayloadMapperContract::MODE_REPLACE.
     *
     * @return string
     */
    public function mode(): string
    {
        return PayloadMapperContract::MODE_MERGE;
    }

    /**
     * Return field keys to remove from the package default map.
     *
     * Default behavior:
     * - Remove nothing.
     *
     * This method is mainly useful in merge mode, where a consumer
     * wants to keep the package defaults but explicitly exclude one
     * or more mapped fields.
     *
     * Example:
     * - ['name', 'password_confirmation']
     *
     * @return array<int, string>
     */
    public function remove(): array
    {
        return [];
    }
}