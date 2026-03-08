{{--
/**
 * Component: Form Fields
 *
 * Schema-driven renderer for a collection of normalized AuthKit form fields.
 *
 * Purpose:
 * - Renders an ordered set of resolved fields using the configured single-field
 *   renderer component.
 * - Keeps page templates clean by centralizing field-loop rendering in one place.
 *
 * Expected input:
 * - $fields should be an ordered array of normalized field definitions,
 *   typically produced by the AuthKit form schema resolver.
 *
 * Notes:
 * - Each field is rendered through the configured "field" component alias.
 * - Hidden fields remain the responsibility of the single-field renderer.
 * - This component does not render the submit button; page templates or form
 *   wrappers should render submit actions separately using the resolved schema.
 *
 * Props:
 * - fields: Ordered array of normalized field definition arrays.
 * - unstyled: When true, passes unstyled mode down to each rendered field.
 */
--}}

@props([
    'fields' => [],
    'unstyled' => false,
])

@php
    $components = (array) config('authkit.components', []);
    $fieldComponent = (string) ($components['field'] ?? 'authkit::form.field');
    $resolvedFields = is_array($fields) ? $fields : [];
@endphp

@foreach ($resolvedFields as $field)
    @if (is_array($field))
        <x-dynamic-component
                :component="$fieldComponent"
                :field="$field"
                :unstyled="$unstyled"
        />
    @endif
@endforeach