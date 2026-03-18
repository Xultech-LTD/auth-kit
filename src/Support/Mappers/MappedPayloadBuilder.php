<?php

namespace Xul\AuthKit\Support\Mappers;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use JsonException;
use Xul\AuthKit\Support\Resolvers\PayloadMapperResolver;

/**
 * MappedPayloadBuilder
 *
 * Builds the normalized payload consumed by AuthKit actions from already
 * validated request input and the resolved mapper definition set for a context.
 *
 * Responsibilities:
 * - Resolve the effective field mapping definitions for a given context.
 * - Read source values from validated input using mapper field definitions.
 * - Skip fields that are explicitly excluded from the mapped payload.
 * - Apply supported built-in transforms to field values.
 * - Place transformed values into the configured payload bucket and target key.
 * - Expose helper methods for determining which mapped attributes are marked
 *   as persistable by the active mapper definitions.
 *
 * Normalized payload structure:
 * - attributes:
 *   Values typically used for business mutation or persistence.
 * - options:
 *   Behavioral flags or execution options for the action.
 * - meta:
 *   Supporting context that should not normally be persisted directly.
 *
 * Important notes:
 * - This builder assumes the incoming input has already passed validation.
 * - This builder does not perform persistence.
 * - This builder does not know anything about models directly.
 * - Persistence intent is inferred only from mapper metadata such as:
 *   - bucket
 *   - include
 *   - persist
 *
 * Supported built-in transforms:
 * - trim
 * - lower_trim
 * - hash
 * - boolean
 * - array
 * - encrypt
 * - int
 * - float
 * - json_decode
 */
final class MappedPayloadBuilder
{
    /**
     * Build the normalized mapped payload for a context.
     *
     * The effective mapping definitions are resolved through
     * PayloadMapperResolver, then applied field-by-field to the validated
     * input array.
     *
     * Behavior:
     * - Only included fields are processed.
     * - Only source keys present in the validated input are processed.
     * - Field values may be transformed before being written into the payload.
     * - Destination bucket and target key are normalized before assignment.
     *
     * @param string $context
     * @param array<string, mixed> $validated
     * @return array{
     *     attributes: array<string, mixed>,
     *     options: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    public static function build(string $context, array $validated): array
    {
        $definitions = PayloadMapperResolver::resolveDefinitions($context);

        $payload = [
            'attributes' => [],
            'options' => [],
            'meta' => [],
        ];

        foreach ($definitions as $field => $definition) {
            if (! is_string($field) || $field === '' || ! is_array($definition)) {
                continue;
            }

            if (! self::normalizeInclude($definition['include'] ?? true)) {
                continue;
            }

            $source = self::normalizeSource($field, $definition['source'] ?? null);

            if (! array_key_exists($source, $validated)) {
                continue;
            }

            $value = $validated[$source];
            $value = self::applyTransform($value, $definition['transform'] ?? null, $field);

            $bucket = self::normalizeBucket($definition['bucket'] ?? null);
            $target = self::normalizeTarget($field, $definition['target'] ?? null);

            self::putValue(
                payload: $payload,
                bucket: $bucket,
                target: $target,
                value: $value,
                flatten: (bool) ($definition['flatten'] ?? false),
                asArray: (bool) ($definition['as_array'] ?? false),
            );
        }

        return $payload;
    }

    /**
     * Resolve the persistable destination target keys for a mapper context.
     *
     * A field is considered persistable only when all of the following are true:
     * - include is truthy
     * - persist is explicitly true
     * - bucket resolves to "attributes"
     *
     * The returned values are the normalized destination target keys, not the
     * raw source input keys.
     *
     * Example:
     * If a mapper definition is:
     * [
     *     'email' => [
     *         'source' => 'email',
     *         'target' => 'login_email',
     *         'bucket' => 'attributes',
     *         'include' => true,
     *         'persist' => true,
     *     ],
     * ]
     *
     * then this method will return:
     * - ['login_email']
     *
     * @param string $context
     * @return array<int, string>
     */
    public static function persistableTargets(string $context): array
    {
        $definitions = PayloadMapperResolver::resolveDefinitions($context);

        $targets = [];

        foreach ($definitions as $field => $definition) {
            if (! is_string($field) || $field === '' || ! is_array($definition)) {
                continue;
            }

            $include = self::normalizeInclude($definition['include'] ?? true);
            $persist = (bool) ($definition['persist'] ?? false);
            $bucket = self::normalizeBucket($definition['bucket'] ?? null);

            if (! $include || ! $persist || $bucket !== 'attributes') {
                continue;
            }

            $target = self::normalizeTarget($field, $definition['target'] ?? null);

            if ($target !== '') {
                $targets[] = $target;
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * Resolve the persistable mapped attribute values from a full normalized payload.
     *
     * This is a convenience helper for actions that already have a built payload
     * and want only the subset of mapped attributes that the current mapper marks
     * as persistable.
     *
     * Behavior:
     * - Reads the "attributes" bucket from the provided payload.
     * - Resolves persistable target keys for the given context.
     * - Returns only matching attribute pairs.
     *
     * @param string $context
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function persistableAttributesFromPayload(string $context, array $payload): array
    {
        $attributes = $payload['attributes'] ?? [];

        if (! is_array($attributes) || $attributes === []) {
            return [];
        }

        $persistableTargets = self::persistableTargets($context);

        if ($persistableTargets === []) {
            return [];
        }

        $resolved = [];

        foreach ($persistableTargets as $target) {
            if (array_key_exists($target, $attributes)) {
                $resolved[$target] = $attributes[$target];
            }
        }

        return $resolved;
    }

    /**
     * Normalize the include flag from a mapper definition.
     *
     * Non-boolean values default to true so definitions remain permissive unless
     * they explicitly opt out.
     *
     * @param mixed $include
     * @return bool
     */
    protected static function normalizeInclude(mixed $include): bool
    {
        return is_bool($include) ? $include : true;
    }

    /**
     * Normalize the source input key for a mapped field.
     *
     * When the mapper definition does not provide a valid source key, the field
     * definition key itself is used as the source key.
     *
     * @param string $fallback
     * @param mixed $source
     * @return string
     */
    protected static function normalizeSource(string $fallback, mixed $source): string
    {
        if (! is_string($source)) {
            return $fallback;
        }

        $source = trim($source);

        return $source !== '' ? $source : $fallback;
    }

    /**
     * Normalize the destination bucket for a mapped field.
     *
     * Supported buckets:
     * - attributes
     * - options
     * - meta
     *
     * Unknown or empty values fall back to "attributes".
     *
     * @param mixed $bucket
     * @return string
     */
    protected static function normalizeBucket(mixed $bucket): string
    {
        if (! is_string($bucket)) {
            return 'attributes';
        }

        $bucket = trim($bucket);

        return match ($bucket) {
            'attributes', 'options', 'meta' => $bucket,
            default => 'attributes',
        };
    }

    /**
     * Normalize the destination target key for a mapped field.
     *
     * When the mapper definition does not provide a valid target key, the field
     * definition key itself is used as the destination key.
     *
     * @param string $fallback
     * @param mixed $target
     * @return string
     */
    protected static function normalizeTarget(string $fallback, mixed $target): string
    {
        if (! is_string($target)) {
            return $fallback;
        }

        $target = trim($target);

        return $target !== '' ? $target : $fallback;
    }

    /**
     * Apply a configured transform to a mapped value.
     *
     * Supported transform identifiers:
     * - trim
     * - lower_trim
     * - hash
     * - boolean
     * - array
     * - encrypt
     * - int
     * - float
     * - json_decode
     *
     * Unsupported non-null transform values throw an exception so mapper
     * misconfiguration is surfaced immediately and explicitly.
     *
     * @param mixed $value
     * @param mixed $transform
     * @param string $field
     * @return mixed
     */
    protected static function applyTransform(mixed $value, mixed $transform, string $field): mixed
    {
        if ($transform === null) {
            return $value;
        }

        if (! is_string($transform)) {
            throw new InvalidArgumentException(sprintf(
                'AuthKit: mapper transform for field [%s] must be a string or null.',
                $field
            ));
        }

        $transform = trim($transform);

        if ($transform === '') {
            return $value;
        }

        return match ($transform) {
            'trim' => self::transformTrim($value),
            'lower_trim' => self::transformLowerTrim($value),
            'hash' => self::transformHash($value),
            'boolean' => self::transformBoolean($value),
            'array' => self::transformArray($value),
            'encrypt' => self::transformEncrypt($value),
            'int' => self::transformInt($value),
            'float' => self::transformFloat($value),
            'json_decode' => self::transformJsonDecode($value, $field),
            default => throw new InvalidArgumentException(sprintf(
                'AuthKit: unsupported mapper transform [%s] for field [%s].',
                $transform,
                $field
            )),
        };
    }

    /**
     * Trim surrounding whitespace from string values.
     *
     * Non-string values are returned unchanged.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function transformTrim(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Lowercase and trim string values.
     *
     * Non-string values are returned unchanged.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function transformLowerTrim(mixed $value): mixed
    {
        return is_string($value)
            ? mb_strtolower(trim($value))
            : $value;
    }

    /**
     * Hash scalar values for secure storage.
     *
     * Null values are returned unchanged.
     * Non-scalar, non-null values are rejected.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function transformHash(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return Hash::make((string) $value);
        }

        throw new InvalidArgumentException(
            'AuthKit: hash transform expects a scalar or null value.'
        );
    }

    /**
     * Convert a value to boolean using common truthy and falsy forms.
     *
     * Supported string forms:
     * - true / false
     * - 1 / 0
     * - yes / no
     * - on / off
     *
     * Unrecognized values fall back to PHP boolean casting semantics.
     *
     * @param mixed $value
     * @return bool
     */
    protected static function transformBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));

            return match ($normalized) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off', '' => false,
                default => (bool) $value,
            };
        }

        return (bool) $value;
    }

    /**
     * Convert a value to array form.
     *
     * Behavior:
     * - arrays are returned unchanged
     * - null becomes an empty array
     * - all other values become a single-item array
     *
     * @param mixed $value
     * @return array<int|string, mixed>
     */
    protected static function transformArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null) {
            return [];
        }

        return [$value];
    }

    /**
     * Encrypt scalar or array values using Laravel's encrypter.
     *
     * Arrays are first JSON-encoded, then encrypted as strings.
     * Null values are returned unchanged.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function transformEncrypt(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value) || is_array($value)) {
            return Crypt::encryptString(
                is_array($value)
                    ? json_encode($value, JSON_THROW_ON_ERROR)
                    : (string) $value
            );
        }

        throw new InvalidArgumentException(
            'AuthKit: encrypt transform expects a scalar, array, or null value.'
        );
    }

    /**
     * Convert a value to integer.
     *
     * Empty string and null are normalized to null.
     *
     * @param mixed $value
     * @return int|null
     */
    protected static function transformInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Convert a value to float.
     *
     * Empty string and null are normalized to null.
     *
     * @param mixed $value
     * @return float|null
     */
    protected static function transformFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * Decode a JSON string into its PHP representation.
     *
     * Behavior:
     * - null and empty string normalize to null
     * - non-string values are returned unchanged
     * - invalid JSON throws an explicit InvalidArgumentException
     *
     * JSON objects are decoded into associative arrays.
     *
     * @param mixed $value
     * @param string $field
     * @return mixed
     */
    protected static function transformJsonDecode(mixed $value, string $field): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(sprintf(
                'AuthKit: json_decode transform failed for field [%s]: %s',
                $field,
                $exception->getMessage()
            ), previous: $exception);
        }
    }

    /**
     * Write a transformed value into the normalized payload structure.
     *
     * Behavior:
     * - When asArray=true:
     *   the value is first normalized into array form.
     * - When flatten=true and the final value is an array:
     *   the array is merged into the destination bucket directly.
     * - Otherwise:
     *   the final value is assigned to payload[bucket][target].
     *
     * This method does not interpret persistability. It is only responsible for
     * writing the already-transformed value into the normalized payload shape.
     *
     * @param array<string, array<string, mixed>> $payload
     * @param string $bucket
     * @param string $target
     * @param mixed $value
     * @param bool $flatten
     * @param bool $asArray
     * @return void
     */
    protected static function putValue(
        array &$payload,
        string $bucket,
        string $target,
        mixed $value,
        bool $flatten = false,
        bool $asArray = false
    ): void {
        if ($asArray) {
            $value = self::transformArray($value);
        }

        if ($flatten && is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) || is_int($key)) {
                    $payload[$bucket][(string) $key] = $item;
                }
            }

            return;
        }

        $payload[$bucket][$target] = $value;
    }
}