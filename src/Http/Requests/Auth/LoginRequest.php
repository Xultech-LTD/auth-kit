<?php

namespace Xul\AuthKit\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Xul\AuthKit\Support\Resolvers\FormSchemaResolver;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

final class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize identity field before validation.
     */
    protected function prepareForValidation(): void
    {
        $field = (string) data_get(config('authkit.identity.login', []), 'field', 'email');
        $normalize = (string) data_get(config('authkit.identity.login', []), 'normalize', 'lower');

        $val = (string) $this->input($field, '');

        if ($normalize === 'trim') {
            $val = trim($val);
        } elseif ($normalize === 'lower') {
            $val = mb_strtolower(trim($val));
        }

        $this->merge([
            $field => $val,
        ]);
    }

    /**
     * Get the validation rules for the request.
     *
     * Rules are built from:
     * - authkit.schemas.login (default schema)
     * - optional rules provider override: authkit.validation.providers.login
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schema = FormSchemaResolver::resolve('login');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'login',
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
        $schema = FormSchemaResolver::resolve('login');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'login',
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
        $schema = FormSchemaResolver::resolve('login');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'login',
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

        $identityField = (string) data_get(config('authkit.identity.login', []), 'field', 'email');

        if (in_array($identityField, $fields, true)) {
            $rules[$identityField] = ['required', 'string'];
        } else {
            $rules[$identityField] = ['required', 'string'];
        }

        if (in_array('password', $fields, true)) {
            $rules['password'] = ['required', 'string'];
        } else {
            $rules['password'] = ['required', 'string'];
        }

        if (in_array('remember', $fields, true)) {
            $rules['remember'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}