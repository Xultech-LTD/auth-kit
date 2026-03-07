<?php

namespace Xul\AuthKit\Contracts\Forms;

interface FieldComponentResolverContract
{
    /**
     * Resolve the Blade component alias/view used to render a field.
     *
     * Expected output examples:
     * - authkit::form.input
     * - authkit::form.select
     * - authkit::form.textarea
     * - authkit::form.checkbox
     *
     * @param  array<string, mixed>  $field
     */
    public function resolve(array $field): string;
}