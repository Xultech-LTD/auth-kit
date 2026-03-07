<?php

namespace Xul\AuthKit\Support\Resolvers;

use Xul\AuthKit\Contracts\Forms\FieldComponentResolverContract;
use Xul\AuthKit\Contracts\Forms\FieldOptionsResolverContract;
use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;

final class FormSchemaResolver implements FormSchemaResolverContract
{
    public function __construct(
        protected FieldOptionsResolverContract $optionsResolver,
        protected FieldComponentResolverContract $componentResolver,
    ) {
    }

    /**
     * Resolve a form schema by context.
     *
     * @param  string  $context
     * @return array<string, mixed>
     */
    public function resolve(string $context): array
    {
        return $this->resolveWithRuntime($context, []);
    }

    /**
     * Resolve a form schema by context with runtime values.
     *
     * @param  string  $context
     * @param  array<string, mixed>  $runtime
     * @return array<string, mixed>
     */
    public function resolveWithRuntime(string $context, array $runtime = []): array
    {
        $schema = config("authkit.schemas.{$context}");

        if (!is_array($schema)) {
            $schema = [];
        }

        $submit = is_array($schema['submit'] ?? null) ? $schema['submit'] : [];
        $rawFields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];

        $fields = [];

        foreach ($rawFields as $name => $field) {
            if (!is_string($name) || $name === '' || !is_array($field)) {
                continue;
            }

            $resolvedField = FieldDefinitionResolver::resolve($name, $field);

            $resolvedField['options'] = $this->optionsResolver->resolve($resolvedField, $runtime);
            $resolvedField['value'] = FieldValueResolver::resolve($resolvedField, $runtime);
            $resolvedField['checked'] = FieldValueResolver::resolveChecked($resolvedField, $runtime);
            $resolvedField['component'] = $this->componentResolver->resolve($resolvedField);

            $fields[$name] = $resolvedField;
        }

        return [
            'name' => $context,
            'submit' => [
                'label' => $this->normalizeSubmitLabel($submit['label'] ?? null),
            ],
            'fields' => $fields,
        ];
    }

    protected function normalizeSubmitLabel(mixed $label): string
    {
        if (!is_string($label)) {
            return 'Continue';
        }

        $label = trim($label);

        return $label !== '' ? $label : 'Continue';
    }
}