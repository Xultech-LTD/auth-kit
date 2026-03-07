<?php

namespace Xul\AuthKit\Contracts\Forms;

interface FieldOptionsResolverContract
{
    /**
     * Resolve normalized options for a field from any supported source.
     *
     * Supported source types may include:
     * - array
     * - enum
     * - class
     * - model
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $field, array $context = []): array;
}