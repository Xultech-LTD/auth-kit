<?php

namespace Xul\AuthKit\Support;

/**
 * AuthKitSessionKeys
 *
 * Centralized session keys used by AuthKit flows.
 */
final class AuthKitSessionKeys
{
    /**
     * Session key storing the pending two-factor challenge token.
     */
    public const TWO_FACTOR_CHALLENGE = 'authkit.two_factor.challenge';
}