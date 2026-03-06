<?php

namespace Xul\AuthKit\Http\Requests\PasswordReset;

use Illuminate\Foundation\Http\FormRequest;
use Xul\AuthKit\Support\Resolvers\FormSchemaResolver;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

/**
 * ResetPasswordRequest
 *
 * Validates the "reset password" submission (email + token + new password).
 *
 * Schema:
 * - Uses the "password_reset" schema from authkit.schemas to resolve labels and
 *   allow consumer rule overrides through the rules provider system.
 */
final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize identity input before validation.
     *
     * Password reset currently assumes the identity is an email.
     */
    protected function prepareForValidation(): void
    {
        $email = mb_strtolower(trim((string) $this->input('email', '')));

        $this->merge([
            'email' => $email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schema = FormSchemaResolver::resolve('password_reset');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'password_reset',
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
        $schema = FormSchemaResolver::resolve('password_reset');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'password_reset',
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
        $schema = FormSchemaResolver::resolve('password_reset');
        $fields = (array) ($schema['fields'] ?? []);

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'password_reset',
            request: $this,
            schema: $schema,
            defaults: $defaults
        );

        return (array) ($payload['attributes'] ?? []);
    }

    /**
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
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function defaultRules(array $fields): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
        ];
    }
}