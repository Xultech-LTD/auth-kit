<?php

namespace Xul\AuthKit\Http\Requests\App\Settings;

use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;
use Xul\AuthKit\Http\Requests\AuthKitFormRequest;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

/**
 * RegenerateTwoFactorRecoveryCodesRequest
 *
 * Validates the authenticated recovery-code regeneration form.
 *
 * Responsibilities:
 * - Resolve the canonical AuthKit schema for the
 *   two_factor_recovery_regenerate context.
 * - Build default rules from the configured schema.
 * - Allow consumer-defined validation providers to override or extend the
 *   packaged defaults without editing package source.
 *
 * Notes:
 * - This request is schema-driven so consumer schema customization remains
 *   aligned with request validation.
 * - Business rules such as actual OTP verification and recovery-code
 *   regeneration are handled by the action, not by this request.
 */
final class RegenerateTwoFactorRecoveryCodesRequest extends AuthKitFormRequest
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
            context: 'two_factor_recovery_regenerate',
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
            context: 'two_factor_recovery_regenerate',
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
            context: 'two_factor_recovery_regenerate',
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
        return app(FormSchemaResolverContract::class)->resolve('two_factor_recovery_regenerate');
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
     * Build the packaged default rules for the recovery-code regeneration form.
     *
     * Default flow:
     * - code
     *
     * Notes:
     * - Regeneration requires a valid authenticator code.
     * - Actual driver verification is handled by the action.
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