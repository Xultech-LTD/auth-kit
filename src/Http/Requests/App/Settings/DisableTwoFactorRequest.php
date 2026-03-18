<?php

namespace Xul\AuthKit\Http\Requests\App\Settings;

use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;
use Xul\AuthKit\Http\Requests\AuthKitFormRequest;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

/**
 * DisableTwoFactorRequest
 *
 * Validates the authenticated two-factor disable form.
 *
 * Responsibilities:
 * - Resolve the canonical AuthKit schema for the disable-two-factor context.
 * - Support both authenticator-code and recovery-code disable flows on the
 *   same endpoint.
 * - Build default rules from the configured schema.
 * - Allow consumer-defined validation providers to override or extend the
 *   packaged defaults without editing package source.
 *
 * Notes:
 * - This request is schema-driven so consumer schema customization remains
 *   aligned with request validation.
 * - Transport validation intentionally keeps both credential fields optional.
 * - The business rule that at least one credential must be supplied is handled
 *   by the action, not by this request.
 * - Actual driver verification, recovery-code consumption, and state
 *   persistence are also handled by the action layer.
 */
final class DisableTwoFactorRequest extends AuthKitFormRequest
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
     * Get validation rules.
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
            context: $this->context(),
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['rules'] ?? []);
    }

    /**
     * Get validation messages.
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
            context: $this->context(),
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['messages'] ?? []);
    }

    /**
     * Get validation attribute names.
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
            context: $this->context(),
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['attributes'] ?? []);
    }

    /**
     * Resolve the form schema for this request context.
     *
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return app(FormSchemaResolverContract::class)->resolve($this->context());
    }

    /**
     * Resolve the schema/validation context for the disable flow.
     *
     * This endpoint supports both authenticator-code and recovery-code disable
     * submissions through the same action, so the request always resolves the
     * same canonical context.
     *
     * @return string
     */
    protected function context(): string
    {
        return 'two_factor_disable';
    }

    /**
     * Build default attribute names from schema.
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
     * Build the packaged default rules for the disable-two-factor flow.
     *
     * Important behavior:
     * - code is optional at request-validation level
     * - recovery_code is optional at request-validation level
     * - the action enforces that at least one credential is provided
     *
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function defaultRules(array $fields): array
    {
        $rules = [];

        if (in_array('code', $fields, true)) {
            $rules['code'] = ['nullable', 'string'];
        }

        if (in_array('recovery_code', $fields, true)) {
            $rules['recovery_code'] = ['nullable', 'string'];
        }

        return $rules;
    }
}