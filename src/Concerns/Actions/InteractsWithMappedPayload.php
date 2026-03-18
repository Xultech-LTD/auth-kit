<?php

namespace Xul\AuthKit\Concerns\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Xul\AuthKit\Concerns\Model\HasAuthKitMappedPersistence;
use Xul\AuthKit\Support\Mappers\MappedPayloadBuilder;

/**
 * InteractsWithMappedPayload
 *
 * Shared helper for AuthKit actions that consume normalized mapped payloads.
 *
 * Responsibilities:
 * - Extract normalized payload buckets from mapped action input.
 * - Resolve persistable field definitions for a mapper context.
 * - Persist only persistable mapped attributes when the model supports it.
 *
 * Design goals:
 * - Keep action classes small and consistent.
 * - Allow actions to remain forward-compatible when consumers change mapper
 *   definitions and mark additional fields as persistable.
 * - Avoid forcing every action to manually re-implement payload bucket parsing
 *   and persistence checks.
 *
 * Notes:
 * - Persistence is opt-in at both layers:
 *   1. the mapper field definition must set persist => true
 *   2. the model must use HasAuthKitMappedPersistence
 * - Non-persistable contexts such as login still include this trait so the
 *   action remains schema-aware and future-proof.
 */
trait InteractsWithMappedPayload
{
    /**
     * Extract mapped attributes from the normalized payload.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function payloadAttributes(array $input): array
    {
        $attributes = $input['attributes'] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * Extract mapped options from the normalized payload.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function payloadOptions(array $input): array
    {
        $options = $input['options'] ?? [];

        return is_array($options) ? $options : [];
    }

    /**
     * Extract mapped meta from the normalized payload.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function payloadMeta(array $input): array
    {
        $meta = $input['meta'] ?? [];

        return is_array($meta) ? $meta : [];
    }

    /**
     * Resolve persistable mapped values for a context.
     *
     * This filters the mapped payload down to only fields that are:
     * - placed in the attributes bucket
     * - marked persist => true in the mapper definition
     *
     * @param string $context
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function persistableAttributesFor(string $context, array $input): array
    {
        $attributes = $this->payloadAttributes($input);
        $persistableTargets = MappedPayloadBuilder::persistableTargets($context);

        if ($attributes === [] || $persistableTargets === []) {
            return [];
        }

        $persistable = [];

        foreach ($persistableTargets as $target) {
            if (array_key_exists($target, $attributes)) {
                $persistable[$target] = $attributes[$target];
            }
        }

        return $persistable;
    }

    /**
     * Persist mapped attributes to a model when the model supports
     * AuthKit mapped persistence.
     *
     * @param Authenticatable|object $model
     * @param string $context
     * @param array<string, mixed> $input
     * @return void
     */
    protected function persistMappedAttributesIfSupported(
        object $model,
        string $context,
        array $input
    ): void {
        if (! in_array(HasAuthKitMappedPersistence::class, class_uses_recursive($model), true)) {
            return;
        }

        $persistable = $this->persistableAttributesFor($context, $input);

        if ($persistable === []) {
            return;
        }

        /** @var HasAuthKitMappedPersistence $model */
        $model->authKitFillPersistableAttributes($persistable);
    }
}