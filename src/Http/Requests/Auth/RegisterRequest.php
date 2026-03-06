<?php

namespace Xul\AuthKit\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Xul\AuthKit\Support\Resolvers\FormSchemaResolver;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

final class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for the request.
     *
     * Rules are built from:
     * - authkit.schemas.register (default schema)
     * - optional rules provider override: authkit.validation.providers.register
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schema = FormSchemaResolver::resolve('register');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'register',
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
        $schema = FormSchemaResolver::resolve('register');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'register',
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
        $schema = FormSchemaResolver::resolve('register');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'register',
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

        if (in_array('name', $fields, true)) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        if (in_array('email', $fields, true)) {
            $rules['email'] = ['required', 'string', 'email', 'max:255'];
        }

        if (in_array('password', $fields, true)) {
            $rules['password'] = ['required', 'string', Password::defaults()];
        }

        if (in_array('password_confirmation', $fields, true)) {
            $rules['password_confirmation'] = ['required', 'same:password'];
        }

        return $rules;
    }
}