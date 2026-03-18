<?php

namespace Xul\AuthKit\Http\Requests\App\Confirmations;

use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;
use Xul\AuthKit\Http\Requests\AuthKitFormRequest;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

/**
 * ConfirmTwoFactorRequest
 *
 * Validates the authenticated two-factor confirmation form used by AuthKit's
 * step-up security flow.
 *
 * Responsibilities:
 * - Resolve the configured confirm-two-factor schema.
 * - Build schema-aware default validation rules.
 * - Allow consumers to override rules, messages, and attributes through the
 *   configured rules provider system.
 *
 * Notes:
 * - This request is intentionally schema-driven so UI and validation remain
 *   aligned when consumers customize the form definition.
 * - The actual code verification is handled by the action, not by
 *   the request validator.
 */
final class ConfirmTwoFactorRequest extends AuthKitFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schema = $this->schema();
        $fields = array_keys((array) ($schema['fields'] ?? []));

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'confirm_two_factor',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['rules'] ?? []);
    }

    /**
     * Get the validation messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $schema = $this->schema();
        $fields = array_keys((array) ($schema['fields'] ?? []));

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'confirm_two_factor',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['messages'] ?? []);
    }

    /**
     * Get the custom validation attributes for the request.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $schema = $this->schema();
        $fields = array_keys((array) ($schema['fields'] ?? []));

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'confirm_two_factor',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['attributes'] ?? []);
    }

    /**
     * Resolve the configured confirm-two-factor schema.
     *
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return app(FormSchemaResolverContract::class)->resolve('confirm_two_factor');
    }

    /**
     * Build default attribute names from schema labels.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, string>
     */
    protected function defaultAttributes(array $schema): array
    {
        $fields = (array) ($schema['fields'] ?? []);
        $out = [];

        foreach ($fields as $name => $field) {
            if (! is_string($name) || $name === '' || ! is_array($field)) {
                continue;
            }

            $label = $field['label'] ?? null;

            if (is_string($label) && trim($label) !== '') {
                $out[$name] = trim($label);
            }
        }

        return $out;
    }

    /**
     * Build the default validation rules for the confirm-two-factor flow.
     *
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function defaultRules(array $fields): array
    {
        $rules = [];

        if (in_array('code', $fields, true)) {
            $rules['code'] = ['required', 'string'];
        }

        return $rules;
    }
}