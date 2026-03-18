<?php

namespace Xul\AuthKit\Concerns\Model;

/**
 * HasAuthKitMappedPersistence
 *
 * Adds a small, config-agnostic persistence surface for AuthKit actions that
 * need to write mapper-defined persistable attributes onto a model.
 *
 * Responsibilities:
 * - Accept persistable mapped attributes from an AuthKit action.
 * - Write them through setAttribute() when available.
 * - Fall back to direct property assignment for simpler objects.
 * - Persist the model when it supports saving and changed attributes were applied.
 *
 * Notes:
 * - This trait does not decide which fields are persistable.
 * - Persistability is determined by mapper definitions.
 * - Actions should pass only fields already resolved as persistable.
 */
trait HasAuthKitMappedPersistence
{
    /**
     * Fill persistable mapped attributes onto the current model instance.
     *
     * Behavior:
     * - Ignores invalid or empty attribute keys.
     * - Uses setAttribute() when available so Eloquent casting/mutators still apply.
     * - Falls back to direct property assignment for simpler objects.
     * - Saves the model automatically when it supports persistence and has changes.
     *
     * @param array<string, mixed> $attributes
     * @return void
     */
    public function authKitFillPersistableAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            if (method_exists($this, 'setAttribute')) {
                $this->setAttribute($key, $value);

                continue;
            }

            $this->{$key} = $value;
        }

        if (method_exists($this, 'isDirty') && method_exists($this, 'save') && $this->isDirty()) {
            $this->save();
        }
    }
}