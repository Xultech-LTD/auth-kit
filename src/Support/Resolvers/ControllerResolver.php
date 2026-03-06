<?php

namespace Xul\AuthKit\Support\Resolvers;

final class ControllerResolver
{
    /**
     * Resolve a controller class-string from config, with a safe fallback.
     *
     * This allows consuming apps to override AuthKit controllers without publishing routes/controllers.
     *
     * @param  string  $group  Controller group key (e.g. "web" or "api").
     * @param  string  $key    Controller key within the group.
     * @param  class-string  $default Default controller class when no override is provided.
     * @return class-string
     */
    public static function resolve(string $group, string $key, string $default): string
    {
        $candidate = config("authkit.controllers.{$group}.{$key}");

        if (!is_string($candidate) || $candidate === '') {
            return $default;
        }

        if (!class_exists($candidate)) {
            return $default;
        }

        return $candidate;
    }
}