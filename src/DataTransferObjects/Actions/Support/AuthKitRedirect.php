<?php

namespace Xul\AuthKit\DataTransferObjects\Actions\Support;

/**
 * AuthKitRedirect
 *
 * Standardized navigation DTO describing redirect intent for an
 * AuthKit action outcome.
 *
 * Responsibilities:
 * - Represent where the client should be sent next.
 * - Support route-based or fully resolved URL-based navigation targets.
 * - Carry route parameters when the target is route-based.
 * - Provide a stable redirect contract for controllers and responders.
 *
 * Design notes:
 * - The type property identifies how the redirect target should be interpreted.
 * - Supported types are expected to be "route" and "url".
 * - The target property holds the route name or raw URL depending on type.
 * - The parameters property is primarily intended for route-based redirects.
 * - The url property may hold a resolved URL when that is useful to downstream
 *   responders or JSON consumers.
 */
final class AuthKitRedirect
{
    /**
     * Create a new redirect instance.
     *
     * @param string $type
     * @param string $target
     * @param array<string, mixed> $parameters
     * @param string|null $url
     */
    public function __construct(
        public readonly string $type,
        public readonly string $target,
        public readonly array $parameters = [],
        public readonly ?string $url = null,
    ) {}

    /**
     * Create a route-based redirect.
     *
     * @param string $routeName
     * @param array<string, mixed> $parameters
     * @param string|null $url
     * @return self
     */
    public static function route(string $routeName, array $parameters = [], ?string $url = null): self
    {
        return new self('route', $routeName, $parameters, $url);
    }

    /**
     * Create a URL-based redirect.
     *
     * @param string $url
     * @return self
     */
    public static function url(string $url): self
    {
        return new self('url', $url, [], $url);
    }

    /**
     * Determine whether the redirect target is route-based.
     *
     * @return bool
     */
    public function isRoute(): bool
    {
        return $this->type === 'route';
    }

    /**
     * Determine whether the redirect target is URL-based.
     *
     * @return bool
     */
    public function isUrl(): bool
    {
        return $this->type === 'url';
    }

    /**
     * Convert the redirect into an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'target' => $this->target,
            'parameters' => $this->parameters,
            'url' => $this->url,
        ];
    }
}