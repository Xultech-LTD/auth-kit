<?php

namespace Xul\AuthKit\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;
use Xul\AuthKit\Http\Requests\AuthKitFormRequest;
use Xul\AuthKit\Support\AuthKitSessionKeys;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

final class TwoFactorChallengeRequest extends AuthKitFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize and hydrate challenge from session when not provided.
     */
    protected function prepareForValidation(): void
    {
        $challenge = (string) $this->input('challenge', '');

        if ($challenge === '') {
            $challenge = (string) $this->session()->get(AuthKitSessionKeys::TWO_FACTOR_CHALLENGE, '');
        }

        $code = (string) $this->input('code', '');

        $this->merge([
            'challenge' => trim($challenge),
            'code' => trim($code),
        ]);
    }

    /**
     * Get the validation rules for the request.
     *
     * Rules are built from:
     * - authkit.schemas.two_factor_challenge (default schema)
     * - optional rules provider override: authkit.validation.providers.two_factor_challenge
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
            context: 'two_factor_challenge',
            request: $this,
            schema: $schema,
            defaults: $defaults,
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
        $schema = $this->schema();
        $fields = array_keys((array) ($schema['fields'] ?? []));

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'two_factor_challenge',
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
            context: 'two_factor_challenge',
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
        return app(FormSchemaResolverContract::class)->resolve('two_factor_challenge');
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

        $out['challenge'] = $out['challenge'] ?? 'Challenge';
        $out['code'] = $out['code'] ?? 'Authentication code';

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
        return [
            'challenge' => ['required', 'string'],
            'code' => ['required', 'string'],
        ];
    }
}