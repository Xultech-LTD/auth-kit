<?php

namespace Xul\AuthKit\Contracts\Forms;

interface FieldValueProviderContract
{
    /**
     * Resolve the value for a field.
     *
     * Resolver precedence is handled elsewhere by the field value resolver.
     * This contract only provides the value when the configured value_resolver is used.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $context
     * @return mixed
     */
    public function resolve(array $field, array $context = []): mixed;
}