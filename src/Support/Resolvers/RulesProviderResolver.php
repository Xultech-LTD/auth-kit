<?php

namespace Xul\AuthKit\Support\Resolvers;

use Illuminate\Http\Request;
use Xul\AuthKit\Contracts\Validation\RulesProviderContract;

final class RulesProviderResolver
{
    /**
     * Resolve a rules provider instance for a given validation context.
     *
     * @param  string  $context  Validation context key (e.g. "login", "register").
     * @return RulesProviderContract|null
     */
    public static function resolve(string $context): ?RulesProviderContract
    {
        $candidate = config("authkit.validation.providers.{$context}");

        if (!is_string($candidate) || $candidate === '') {
            return null;
        }

        if (!class_exists($candidate)) {
            return null;
        }

        $provider = app($candidate);

        return $provider instanceof RulesProviderContract ? $provider : null;
    }

    /**
     * Resolve rules/messages/attributes for a context.
     *
     * If a provider exists, it will be used. Otherwise the defaults are returned.
     *
     * @param  string  $context
     * @param  Request  $request
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $defaults
     * @return array{rules: array<string, mixed>, messages: array<string, string>, attributes: array<string, string>}
     */
    public static function resolvePayload(
        string $context,
        Request $request,
        array $schema,
        array $defaults
    ): array {
        $provider = self::resolve($context);

        if (!$provider) {
            return [
                'rules' => (array) ($defaults['rules'] ?? []),
                'messages' => (array) ($defaults['messages'] ?? []),
                'attributes' => (array) ($defaults['attributes'] ?? []),
            ];
        }

        return [
            'rules' => (array) $provider->rules($request, $schema, (array) ($defaults['rules'] ?? [])),
            'messages' => (array) $provider->messages($request, $schema, (array) ($defaults['messages'] ?? [])),
            'attributes' => (array) $provider->attributes($request, $schema, (array) ($defaults['attributes'] ?? [])),
        ];
    }
}