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
     * Use this when no runtime field hydration is required.
     *
     * @param  string  $context
     * @return array<string, mixed>
     */
    public function resolve(string $context): array;

    /**
     * Resolve a form schema by context and apply runtime values before returning
     * the normalized structure.
     *
     * Intended for page/controller-driven flows where one or more fields must be
     * hydrated dynamically at render time, such as hidden challenge references,
     * reset tokens, email context, or other request-specific state.
     *
     * Example:
     * [
     *     'challenge' => 'abc123',
     *     'email' => 'user@example.com',
     * ]
     *
     * @param  string  $context
     * @param  array<string, mixed>  $runtime
     * @return array<string, mixed>
     */
    public function resolveWithRuntime(string $context, array $runtime = []): array;
}