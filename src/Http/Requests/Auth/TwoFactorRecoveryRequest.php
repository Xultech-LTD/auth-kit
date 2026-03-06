<?php

namespace Xul\AuthKit\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\Resolvers\FormSchemaResolver;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

final class TwoFactorRecoveryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize recovery code before validation.
     */
    protected function prepareForValidation(): void
    {
        $challenge = (string) $this->input('challenge', '');

        if ($challenge === '') {
            $challenge = (string) $this->session()->get(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, '');
        }

        $code = (string) $this->input('recovery_code', '');

        $this->merge([
            'challenge' => trim($challenge),
            'recovery_code' => trim($code),
        ]);
    }

    /**
     * Get the validation rules for the request.
     *
     * Rules are built from:
     * - authkit.schemas.two_factor_recovery (default schema)
     * - optional rules provider override: authkit.validation.providers.two_factor_recovery
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schema = FormSchemaResolver::resolve('two_factor_recovery');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'two_factor_recovery',
            request: $this,
            schema: $schema,
            defaults: $defaults
        );

        return (array) ($payload['rules'] ?? []);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $schema = FormSchemaResolver::resolve('two_factor_recovery');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'two_factor_recovery',
            request: $this,
            schema: $schema,
            defaults: $defaults
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
        $schema = FormSchemaResolver::resolve('two_factor_recovery');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'two_factor_recovery',
            request: $this,
            schema: $schema,
            defaults: $defaults
        );

        return (array) ($payload['attributes'] ?? []);
    }

    /**
     * Build default attribute names from schema.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, string>
     */
    protected function defaultAttributes(array $schema): array
    {
        $labels = (array) ($schema['labels'] ?? []);
        $out = [];

        foreach ($labels as $k => $v) {
            if (is_string($k) && $k !== '' && is_string($v) && $v !== '') {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /**
     * Build sensible default rules based on schema fields.
     *
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function defaultRules(array $fields): array
    {
        $rules = [];

        if (in_array('challenge', $fields, true)) {
            $rules['challenge'] = ['required', 'string', 'min:8'];
        } else {
            $rules['challenge'] = ['required', 'string', 'min:8'];
        }

        if (in_array('recovery_code', $fields, true)) {
            $rules['recovery_code'] = ['required', 'string', 'min:4'];
        } else {
            $rules['recovery_code'] = ['required', 'string', 'min:4'];
        }

        if (in_array('remember', $fields, true)) {
            $rules['remember'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}