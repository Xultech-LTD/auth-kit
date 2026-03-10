<?php

namespace Xul\AuthKit\DataTransferObjects\Actions\Support;

/**
 * AuthKitFlowStep
 *
 * Standardized flow-state DTO describing the next application step
 * in an AuthKit authentication or recovery journey.
 *
 * Responsibilities:
 * - Provide a stable flow identifier for action outcomes.
 * - Reduce reliance on scattered boolean flags across actions.
 * - Allow controllers, responders, and frontend consumers to switch
 *   on one explicit next-step value.
 * - Carry optional metadata relevant to the flow transition.
 *
 * Typical step values:
 * - completed
 * - failed
 * - login_required
 * - email_verification_required
 * - email_verification_notice
 * - two_factor_required
 * - two_factor_recovery_required
 * - password_reset_required
 * - password_reset_token_required
 * - password_reset_completed
 * - registration_completed
 *
 * Design notes:
 * - The name property is the canonical flow identifier.
 * - The label property is optional and may be used for descriptive
 *   presentation or debugging.
 * - The metadata property may carry non-sensitive flow context that
 *   is safe for general orchestration use.
 */
final class AuthKitFlowStep
{
    /**
     * Create a new flow-step instance.
     *
     * @param string $name
     * @param string|null $label
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $label = null,
        public readonly array $meta = [],
    ) {}

    /**
     * Create a completed flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function completed(?string $label = null, array $meta = []): self
    {
        return new self('completed', $label, $meta);
    }

    /**
     * Create a failed flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function failed(?string $label = null, array $meta = []): self
    {
        return new self('failed', $label, $meta);
    }

    /**
     * Create an email-verification-required flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function emailVerificationRequired(?string $label = null, array $meta = []): self
    {
        return new self('email_verification_required', $label, $meta);
    }

    /**
     * Create an email-verification-notice flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function emailVerificationNotice(?string $label = null, array $meta = []): self
    {
        return new self('email_verification_notice', $label, $meta);
    }

    /**
     * Create a two-factor-required flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function twoFactorRequired(?string $label = null, array $meta = []): self
    {
        return new self('two_factor_required', $label, $meta);
    }

    /**
     * Create a two-factor-recovery-required flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function twoFactorRecoveryRequired(?string $label = null, array $meta = []): self
    {
        return new self('two_factor_recovery_required', $label, $meta);
    }

    /**
     * Create a password-reset-required flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function passwordResetRequired(?string $label = null, array $meta = []): self
    {
        return new self('password_reset_required', $label, $meta);
    }

    /**
     * Create a password-reset-token-required flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function passwordResetTokenRequired(?string $label = null, array $meta = []): self
    {
        return new self('password_reset_token_required', $label, $meta);
    }

    /**
     * Create a password-reset-completed flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function passwordResetCompleted(?string $label = null, array $meta = []): self
    {
        return new self('password_reset_completed', $label, $meta);
    }

    /**
     * Create a registration-completed flow step.
     *
     * @param string|null $label
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function registrationCompleted(?string $label = null, array $meta = []): self
    {
        return new self('registration_completed', $label, $meta);
    }

    /**
     * Determine whether the current step name matches the given value.
     *
     * @param string $name
     * @return bool
     */
    public function is(string $name): bool
    {
        return $this->name === $name;
    }

    /**
     * Convert the flow step into an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'meta' => $this->meta,
        ];
    }
}