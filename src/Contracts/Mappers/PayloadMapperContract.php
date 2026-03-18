<?php

namespace Xul\AuthKit\Contracts\Mappers;

/**
 * PayloadMapperContract
 *
 * Describes how validated request input should be translated into the
 * normalized payload structure consumed by an AuthKit action context.
 *
 * Responsibilities:
 * - declare the logical mapper context
 * - declare the related schema context, where applicable
 * - indicate whether the mapper should merge with package defaults
 *   or fully replace them
 * - provide field mapping definitions keyed by schema/request field name
 * - optionally remove package-default field mappings in merge mode
 *
 * Notes:
 * - Implementations do not execute persistence themselves.
 * - Implementations return mapping definitions only.
 * - Mapping execution is handled by a separate resolver/executor layer.
 * - The `persist` flag is metadata consumed by the mapped payload builder
 *   so downstream actions can know which mapped attributes are intended
 *   for model persistence.
 */
interface PayloadMapperContract
{
    /**
     * Merge consumer definitions with package defaults.
     */
    public const MODE_MERGE = 'merge';

    /**
     * Ignore package defaults and use only consumer definitions.
     */
    public const MODE_REPLACE = 'replace';

    /**
     * Return the logical mapper context.
     *
     * Examples:
     * - login
     * - register
     * - password_update
     * - two_factor_confirm
     *
     * @return string
     */
    public function context(): string;

    /**
     * Return the schema context used by this mapper.
     *
     * In most cases this matches the mapper context, but it is kept explicit
     * so the mapping layer can intentionally point to a different schema if needed.
     *
     * Return null when the context does not rely on a visible schema.
     *
     * @return string|null
     */
    public function schema(): ?string;

    /**
     * Return the mapper mode.
     *
     * Supported values:
     * - self::MODE_MERGE
     * - self::MODE_REPLACE
     *
     * Behavior:
     * - merge   : package default definitions are loaded first, then consumer
     *             definitions are merged and removals are applied
     * - replace : package defaults are ignored and only this mapper's
     *             definitions are used
     *
     * @return string
     */
    public function mode(): string;

    /**
     * Return field mapping definitions keyed by schema/request field name.
     *
     * Definition shape is intentionally array-based so consumers may add,
     * remove, or fully replace mapping rules without extending internal classes.
     *
     * Recommended keys per field definition:
     * - source   : string|null  Source field key. Defaults to the array key.
     * - target   : string|null  Destination key in the mapped payload.
     * - bucket   : string|null  Payload bucket such as attributes, options, meta.
     * - include  : bool         Whether the field should be included at all.
     * - persist  : bool         Whether the mapped field is intended for persistence.
     * - transform: string|class-string|callable|null
     * - hash     : bool
     * - encrypt  : bool
     * - as_array : bool
     * - flatten  : bool
     *
     * Example:
     * [
     *     'email' => [
     *         'source' => 'email',
     *         'target' => 'email',
     *         'bucket' => 'attributes',
     *         'include' => true,
     *         'persist' => true,
     *         'transform' => 'lower_trim',
     *     ],
     * ]
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array;

    /**
     * Return default field keys to remove when mode() is MODE_MERGE.
     *
     * In MODE_REPLACE this should usually return an empty array.
     *
     * Example:
     * - ['name', 'password_confirmation']
     *
     * @return array<int, string>
     */
    public function remove(): array;
}