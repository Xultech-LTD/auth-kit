<?php

namespace Xul\AuthKit\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;
use Xul\AuthKit\Http\Requests\AuthKitFormRequest;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

/**
 * TwoFactorResendRequest
 *
 * Validates resend requests for a pending login challenge.
 */
final class TwoFactorResendRequest extends AuthKitFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize challenge before validation.
     */
    protected function prepareForValidation(): void
    {
        $email = (string) $this->input('email', '');

        $normalize = (string) data_get(config('authkit.identity.login', []), 'normalize', null);

        if ($normalize === 'lower') {
            $email = mb_strtolower($email);
        }

        $this->merge([
            'email' => trim($email),
        ]);
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
            context: 'two_factor_resend',
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
            context: 'two_factor_resend',
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
            context: 'two_factor_resend',
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
        return app(FormSchemaResolverContract::class)->resolve('two_factor_resend');
    }

    /**
     * @param array<string, mixed> $schema
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
     * @param array<int, string> $fields
     * @return array<string, mixed>
     */
    protected function defaultRules(array $fields): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }
}