<?php

namespace Xul\AuthKit\Contracts\Validation;

use Illuminate\Http\Request;

interface RulesProviderContract
{
    /**
     * Provide validation rules for a given context.
     *
     * Providers may either:
     * - Return the full rules array (recommended), or
     * - Start from $defaults and modify/extend it.
     *
     * @param  Request  $request
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function rules(Request $request, array $schema, array $defaults): array;

    /**
     * Provide custom validation messages for a given context.
     *
     * Providers may either:
     * - Return the full messages array, or
     * - Start from $defaults and modify/extend it.
     *
     * @param  Request  $request
     * @param  array<string, mixed>  $schema
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    public function messages(Request $request, array $schema, array $defaults): array;

    /**
     * Provide custom attribute names for a given context.
     *
     * Providers may either:
     * - Return the full attributes array, or
     * - Start from $defaults and modify/extend it.
     *
     * @param  Request  $request
     * @param  array<string, mixed>  $schema
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    public function attributes(Request $request, array $schema, array $defaults): array;
}