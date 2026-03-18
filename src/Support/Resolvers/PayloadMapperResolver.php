<?php

namespace Xul\AuthKit\Support\Resolvers;

use InvalidArgumentException;
use Xul\AuthKit\Contracts\Mappers\PayloadMapperContract;
use Xul\AuthKit\Support\Mappers\App\Confirmations\ConfirmPasswordPayloadMapper;
use Xul\AuthKit\Support\Mappers\App\Confirmations\ConfirmTwoFactorPayloadMapper;
use Xul\AuthKit\Support\Mappers\Auth\LoginPayloadMapper;
use Xul\AuthKit\Support\Mappers\Auth\RegisterPayloadMapper;
use Xul\AuthKit\Support\Mappers\Auth\TwoFactorChallengePayloadMapper;
use Xul\AuthKit\Support\Mappers\Auth\TwoFactorRecoveryPayloadMapper;
use Xul\AuthKit\Support\Mappers\Auth\TwoFactorResendPayloadMapper;
use Xul\AuthKit\Support\Mappers\EmailVerification\SendEmailVerificationPayloadMapper;
use Xul\AuthKit\Support\Mappers\EmailVerification\VerifyEmailTokenPayloadMapper;
use Xul\AuthKit\Support\Mappers\PasswordReset\ForgotPasswordPayloadMapper;
use Xul\AuthKit\Support\Mappers\PasswordReset\ResetPasswordPayloadMapper;
use Xul\AuthKit\Support\Mappers\PasswordReset\VerifyPasswordResetTokenPayloadMapper;

/**
 * PayloadMapperResolver
 *
 * Resolves payload mapper classes and effective mapping definitions
 * for AuthKit action/form contexts.
 *
 * Responsibilities:
 * - Resolve the package default mapper for a context when one exists.
 * - Resolve a consumer-configured custom mapper for a context when provided.
 * - Validate that resolved mapper classes implement PayloadMapperContract.
 * - Determine the effective schema key used by a mapper context.
 * - Build the effective field definition set by applying merge or replace mode.
 *
 * Resolution flow:
 * - When no custom mapper class is configured, AuthKit uses its package default
 *   mapper for the requested context when available.
 * - When a custom mapper class is configured:
 *   - merge mode:
 *     package defaults are loaded first, then custom removals and custom
 *     definitions are applied on top.
 *   - replace mode:
 *     package defaults are ignored and only the custom definitions are used.
 *
 * Notes:
 * - This resolver returns mapper metadata and definitions only.
 * - It does not execute transformations against validated request data.
 * - It does not perform persistence.
 * - Persistence metadata such as `persist => true` remains part of the
 *   resolved field definitions and is later consumed by MappedPayloadBuilder.
 */
final class PayloadMapperResolver
{
    /**
     * Resolve the effective field mapping definitions for a context.
     *
     * @param  string  $context
     * @return array<string, array<string, mixed>>
     */
    public static function resolveDefinitions(string $context): array
    {
        $defaultMapper = self::resolveDefault($context);
        $customMapper = self::resolveCustom($context);

        if (! $customMapper) {
            return $defaultMapper?->definitions() ?? [];
        }

        if ($customMapper->mode() === PayloadMapperContract::MODE_REPLACE) {
            return $customMapper->definitions();
        }

        $definitions = $defaultMapper?->definitions() ?? [];

        foreach ($customMapper->remove() as $field) {
            if (is_string($field) && $field !== '') {
                unset($definitions[$field]);
            }
        }

        return array_replace($definitions, $customMapper->definitions());
    }

    /**
     * Resolve the effective schema context for a mapper context.
     *
     * Resolution order:
     * - custom mapper schema when present
     * - configured schema value from authkit.mappers.contexts.{context}.schema
     * - default mapper schema
     * - null
     *
     * @param  string  $context
     * @return string|null
     */
    public static function resolveSchema(string $context): ?string
    {
        $customMapper = self::resolveCustom($context);

        if ($customMapper) {
            return $customMapper->schema();
        }

        $configured = config("authkit.mappers.contexts.{$context}.schema");

        if (is_string($configured)) {
            $configured = trim($configured);

            if ($configured !== '') {
                return $configured;
            }
        }

        return self::resolveDefault($context)?->schema();
    }

    /**
     * Resolve the package default mapper for a context.
     *
     * @param  string  $context
     * @return PayloadMapperContract|null
     */
    public static function resolveDefault(string $context): ?PayloadMapperContract
    {
        $class = self::defaultClassFor($context);

        if ($class === null) {
            return null;
        }

        $mapper = app($class);

        if (! $mapper instanceof PayloadMapperContract) {
            throw new InvalidArgumentException(sprintf(
                'AuthKit: default payload mapper [%s] must implement %s.',
                $class,
                PayloadMapperContract::class
            ));
        }

        return $mapper;
    }

    /**
     * Resolve a consumer-configured custom mapper for a context.
     *
     * @param  string  $context
     * @return PayloadMapperContract|null
     */
    public static function resolveCustom(string $context): ?PayloadMapperContract
    {
        $class = config("authkit.mappers.contexts.{$context}.class");

        if (! is_string($class) || trim($class) === '') {
            return null;
        }

        $class = trim($class);

        if (! class_exists($class)) {
            throw new InvalidArgumentException(sprintf(
                'AuthKit: payload mapper class [%s] for context [%s] does not exist.',
                $class,
                $context
            ));
        }

        $mapper = app($class);

        if (! $mapper instanceof PayloadMapperContract) {
            throw new InvalidArgumentException(sprintf(
                'AuthKit: payload mapper [%s] for context [%s] must implement %s.',
                $class,
                $context,
                PayloadMapperContract::class
            ));
        }

        return $mapper;
    }

    /**
     * Resolve the package default mapper class for a given context.
     *
     * Contexts without a package default currently return null and may rely
     * on a consumer-supplied mapper later when needed.
     *
     * @param  string  $context
     * @return class-string<PayloadMapperContract>|null
     */
    protected static function defaultClassFor(string $context): ?string
    {
        return match ($context) {
            'register' => RegisterPayloadMapper::class,
            'login' => LoginPayloadMapper::class,
            'two_factor_challenge' => TwoFactorChallengePayloadMapper::class,
            'two_factor_recovery' => TwoFactorRecoveryPayloadMapper::class,
            'two_factor_resend' => TwoFactorResendPayloadMapper::class,
            'email_verification_send' => SendEmailVerificationPayloadMapper::class,
            'email_verification_token' => VerifyEmailTokenPayloadMapper::class,
            'password_forgot' => ForgotPasswordPayloadMapper::class,
            'password_reset' => ResetPasswordPayloadMapper::class,
            'password_reset_token' => VerifyPasswordResetTokenPayloadMapper::class,
            'confirm_password' => ConfirmPasswordPayloadMapper::class,
            'confirm_two_factor' => ConfirmTwoFactorPayloadMapper::class,
            default => null,
        };
    }
}