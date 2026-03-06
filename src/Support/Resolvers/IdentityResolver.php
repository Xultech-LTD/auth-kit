<?php

namespace Xul\AuthKit\Support\Resolvers;

final class IdentityResolver
{
    /**
     * Resolve the login identity configuration.
     *
     * @return array{field: string, label: string, input_type: string, autocomplete: string, normalize: string|null}
     */
    public static function login(): array
    {
        $field = (string) config('authkit.identity.login.field', 'email');
        $label = (string) config('authkit.identity.login.label', 'Email');
        $type = (string) config('authkit.identity.login.input_type', 'email');
        $autocomplete = (string) config('authkit.identity.login.autocomplete', 'email');

        $normalize = config('authkit.identity.login.normalize', 'lower');
        $normalize = is_string($normalize) && $normalize !== '' ? $normalize : null;

        if ($field === '') {
            $field = 'email';
        }

        return [
            'field' => $field,
            'label' => $label !== '' ? $label : 'Email',
            'input_type' => $type !== '' ? $type : 'text',
            'autocomplete' => $autocomplete !== '' ? $autocomplete : 'username',
            'normalize' => $normalize,
        ];
    }
}