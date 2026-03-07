<?php

namespace Xul\AuthKit\Contracts\Forms;

interface FieldOptionsProviderContract
{
    /**
     * Resolve options for a field.
     *
     * The returned array must be normalized into a render-ready option structure.
     *
     * Supported normalized item shapes:
     *
     * Flat option:
     * [
     *     'value' => 'admin',
     *     'label' => 'Administrator',
     *     'disabled' => false,
     *     'attributes' => [],
     * ]
     *
     * Grouped option:
     * [
     *     'label' => 'Africa',
     *     'options' => [
     *         [
     *             'value' => 'ng',
     *             'label' => 'Nigeria',
     *             'disabled' => false,
     *             'attributes' => [],
     *         ],
     *     ],
     * ]
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $field, array $context = []): array;
}