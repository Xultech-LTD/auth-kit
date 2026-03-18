<?php

namespace Xul\AuthKit\Http\Requests\App\Settings;

use Illuminate\Validation\Rules\Password;
use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;
use Xul\AuthKit\Http\Requests\AuthKitFormRequest;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

/**
 * UpdatePasswordRequest
 *
 * Validates authenticated password update submissions using the configured
 * AuthKit schema and optional rules-provider overrides.
 *
 * Responsibilities:
 * - Resolve the canonical password-update schema from configuration.
 * - Build sensible default validation rules from the schema shape.
 * - Allow consumers to override rules, messages, and attributes through the
 *   configured rules provider pipeline.
 * - Normalize boolean-style checkbox inputs where appropriate.
 *
 * Notes:
 * - This request is intentionally schema-aware so that consumer schema changes
 *   remain aligned with request validation.
 * - Business-rule verification such as checking the current password against
 *   the authenticated user is handled by the action layer.
 */
final class UpdatePasswordRequest extends AuthKitFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The route itself is already protected by authenticated middleware.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the request data for validation.
     *
     * Responsibilities:
     * - Normalize the logout_other_devices checkbox into a boolean-friendly shape.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('logout_other_devices')) {
            $this->merge([
                'logout_other_devices' => filter_var(
                        $this->input('logout_other_devices'),
                        FILTER_VALIDATE_BOOL,
                        FILTER_NULL_ON_FAILURE
                    ) ?? in_array($this->input('logout_other_devices'), ['1', 1, true, 'true', 'on'], true),
            ]);
        }
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
            context: 'password_update',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['rules'] ?? []);
    }

    /**
     * Get the custom messages for validator errors.
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
            context: 'password_update',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['messages'] ?? []);
    }

    /**
     * Get custom attributes for validator errors.
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
            context: 'password_update',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['attributes'] ?? []);
    }

    /**
     * Resolve the configured password-update schema.
     *
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return app(FormSchemaResolverContract::class)->resolve('password_update');
    }

    /**
     * Build default attribute labels from the configured schema.
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
     * Build the default validation rules from the expected schema fields.
     *
     * Notes:
     * - current_password is required when present in the schema.
     * - password uses a conservative default rule set and must be confirmed.
     * - logout_other_devices is optional and boolean when present.
     *
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function defaultRules(array $fields): array
    {
        $rules = [];

        if (in_array('current_password', $fields, true)) {
            $rules['current_password'] = ['required', 'string'];
        }

        if (in_array('password', $fields, true)) {
            $rules['password'] = ['required', 'string', Password::defaults(), 'confirmed'];
        }

        if (in_array('password_confirmation', $fields, true)) {
            $rules['password_confirmation'] = ['required', 'string', Password::defaults()];
        }

        if (in_array('logout_other_devices', $fields, true)) {
            $rules['logout_other_devices'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}