<?php

namespace Xul\AuthKit\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * AuthKitPasswordResetRequested
 *
 * Dispatched when a password reset flow begins (after a token has been created).
 *
 * Typical listeners:
 * - SendPasswordResetNotification (default listener)
 * - Audit logging / analytics
 * - Custom delivery channels (SMS/WhatsApp)
 *
 * Notes:
 * - $token is the raw token. Listeners must not persist it.
 * - $url is provided for link-driver flows.
 * - $user may be null to support privacy-preserving implementations where the
 *   application chooses not to reveal user existence.
 */
final class AuthKitPasswordResetRequested
{
    /**
     * @param string                $driver
     * @param string                $email
     * @param string                $token
     * @param string|null           $url
     * @param Authenticatable|null  $user
     * @param array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $driver,
        public readonly string $email,
        public readonly string $token,
        public readonly ?string $url = null,
        public readonly ?Authenticatable $user = null,
        public readonly array $meta = [],
    ) {}
}