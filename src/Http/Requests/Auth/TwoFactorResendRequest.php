<?php

namespace Xul\AuthKit\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Xul\AuthKit\Support\Resolvers\FormSchemaResolver;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

/**
 * TwoFactorResendRequest
 *
 * Validates resend requests for a pending login challenge.
 */
final class TwoFactorResendRequest extends FormRequest
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
        $schema = FormSchemaResolver::resolve('two_factor_resend');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'two_factor_resend',
            request: $this,
            schema: $schema,
            defaults: $defaults
        );

        return (array) ($payload['rules'] ?? []);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $schema = FormSchemaResolver::resolve('two_factor_resend');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'two_factor_resend',
            request: $this,
            schema: $schema,
            defaults: $defaults
        );

        return (array) ($payload['messages'] ?? []);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $schema = FormSchemaResolver::resolve('two_factor_resend');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'two_factor_resend',
            request: $this,
            schema: $schema,
            defaults: $defaults
        );

        return (array) ($payload['attributes'] ?? []);
    }

    /**
     * @param array<string, mixed> $schema
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