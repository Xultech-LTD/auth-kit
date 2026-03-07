<?php

namespace Xul\AuthKit\Contracts\Forms;

interface FormSchemaResolverContract
{
    /**
     * Resolve a form schema by context into a normalized structure.
     *
     * Expected high-level return shape:
     * [
     *     'name' => 'login',
     *     'submit' => [
     *         'label' => 'Continue',
     *     ],
     *     'fields' => [
     *         'email' => [
     *             'name' => 'email',
     *             'label' => 'Email',
     *             'type' => 'email',
     *             // ...
     *         ],
     *     ],
     * ]
     *
     * @param  string  $context
     * @return array<string, mixed>
     */
    public function resolve(string $context): array;
}