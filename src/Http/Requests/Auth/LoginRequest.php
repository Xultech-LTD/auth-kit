<?php

namespace Xul\AuthKit\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;
use Xul\AuthKit\Http\Requests\AuthKitFormRequest;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

final class LoginRequest extends AuthKitFormRequest
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
            context: 'login',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['rules'] ?? []);
    }

    /**
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
            context: 'login',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['messages'] ?? []);
    }

    /**
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
            context: 'login',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['attributes'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return app(FormSchemaResolverContract::class)->resolve('login');
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
            if (!is_string($name) || $name === '' || !is_array($field)) {
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
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function defaultRules(array $fields): array
    {
        $rules = [];

        $identityField = (string) data_get(config('authkit.identity.login', []), 'field', 'email');

        $rules[$identityField] = ['required', 'string'];
        $rules['password'] = ['required', 'string'];

        if (in_array('remember', $fields, true)) {
            $rules['remember'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}