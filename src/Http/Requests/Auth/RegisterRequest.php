<?php

namespace Xul\AuthKit\Http\Requests\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Xul\AuthKit\Contracts\Forms\FormSchemaResolverContract;
use Xul\AuthKit\Http\Requests\AuthKitFormRequest;
use Xul\AuthKit\Support\Resolvers\RulesProviderResolver;

final class RegisterRequest extends AuthKitFormRequest
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
        $schema = $this->schema();
        $fields = array_keys((array) ($schema['fields'] ?? []));

        $defaults = [
            'rules' => $this->defaultRules($fields),
            'messages' => [],
            'attributes' => $this->defaultAttributes($schema),
        ];

        $payload = RulesProviderResolver::resolvePayload(
            context: 'register',
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
            context: 'register',
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
            context: 'register',
            request: $this,
            schema: $schema,
            defaults: $defaults,
        );

        return (array) ($payload['attributes'] ?? []);
    }

    /**
     * Resolve the canonical register form schema.
     *
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return app(FormSchemaResolverContract::class)->resolve('register');
    }

    /**
     * Build default attribute names from schema labels.
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
     * Build the default validation rules for registration.
     *
     * Design notes:
     * - Rules remain schema-aware so consumers may remove fields from the register
     *   schema without causing irrelevant validation requirements.
     * - The primary identity field may receive a configurable unique rule when
     *   registration uniqueness enforcement is enabled.
     * - Consumers may still replace these defaults entirely through a custom
     *   rules provider configured at authkit.validation.providers.register.
     *
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function defaultRules(array $fields): array
    {
        $rules = [];
        $identityField = $this->identityField();

        if (in_array('name', $fields, true)) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        if (in_array('email', $fields, true)) {
            $rules['email'] = ['required', 'string', 'email', 'max:255'];
        }

        if (
            in_array($identityField, $fields, true)
            && $this->shouldEnforceUniqueIdentity()
        ) {
            $uniqueRule = $this->buildIdentityUniqueRule($identityField);

            if ($uniqueRule !== null) {
                $rules[$identityField] = array_values([
                    ...($rules[$identityField] ?? ['required', 'string', 'max:255']),
                    $uniqueRule,
                ]);
            }
        }

        if (in_array('password', $fields, true)) {
            $rules['password'] = ['required', Password::defaults()];
        }

        if (in_array('password_confirmation', $fields, true)) {
            $rules['password_confirmation'] = ['required', 'same:password'];
        }

        return $rules;
    }

    /**
     * Resolve the configured primary identity field used by AuthKit.
     *
     * Examples:
     * - email
     * - username
     * - phone
     */
    protected function identityField(): string
    {
        return (string) config('authkit.identity.login.field', 'email');
    }

    /**
     * Determine whether AuthKit should apply its default uniqueness rule
     * to the primary registration identity.
     */
    protected function shouldEnforceUniqueIdentity(): bool
    {
        return (bool) config('authkit.registration.enforce_unique_identity', true);
    }

    /**
     * Build the default unique rule for the configured registration identity field.
     *
     * Resolution order:
     * 1. authkit.registration.unique_identity.table / column
     * 2. configured auth provider model table + identity field
     *
     * Returns null when the target table cannot be resolved safely.
     *
     * @param  string  $identityField
     * @return \Illuminate\Validation\Rules\Unique|null
     */
    protected function buildIdentityUniqueRule(string $identityField): ?\Illuminate\Validation\Rules\Unique
    {
        $table = $this->registrationUniqueTable();
        $column = $this->registrationUniqueColumn($identityField);

        if (!is_string($table) || trim($table) === '') {
            return null;
        }

        return Rule::unique($table, $column);
    }

    /**
     * Resolve the database table used for the default registration unique rule.
     *
     * Resolution order:
     * 1. authkit.registration.unique_identity.table
     * 2. configured auth provider model table
     *
     * @return string|null
     */
    protected function registrationUniqueTable(): ?string
    {
        $configured = config('authkit.registration.unique_identity.table');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        $modelClass = $this->authProviderModelClass();

        if (!is_string($modelClass) || $modelClass === '' || !is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        /** @var Model $model */
        $model = new $modelClass();

        return $model->getTable();
    }

    /**
     * Resolve the database column used for the default registration unique rule.
     *
     * Resolution order:
     * 1. authkit.registration.unique_identity.column
     * 2. configured identity field
     *
     * @param  string  $identityField
     * @return string
     */
    protected function registrationUniqueColumn(string $identityField): string
    {
        $configured = config('authkit.registration.unique_identity.column');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return $identityField;
    }

    /**
     * Resolve the Eloquent model class from the configured AuthKit guard provider.
     *
     * This allows AuthKit to infer the users table automatically for the default
     * registration unique rule without forcing consumers to duplicate that table
     * name in configuration.
     *
     * Returns null when:
     * - the configured guard cannot be resolved
     * - the provider is not available
     * - the provider does not expose a model() method
     *
     * @return class-string<Model>|null
     */
    protected function authProviderModelClass(): ?string
    {
        $guard = (string) config('authkit.auth.guard', 'web');

        /** @var \Illuminate\Contracts\Auth\Factory $auth */
        $auth = app(AuthFactory::class);

        $provider = $auth->guard($guard)->getProvider();

        if (!$provider instanceof UserProvider || !method_exists($provider, 'getModel')) {
            return null;
        }

        /** @var mixed $model */
        $model = $provider->getModel();

        return is_string($model) && $model !== '' ? $model : null;
    }
}