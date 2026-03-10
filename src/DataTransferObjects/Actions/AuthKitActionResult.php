<?php

namespace Xul\AuthKit\DataTransferObjects\Actions;

use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitError;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitFlowStep;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitInternalPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitPublicPayload;
use Xul\AuthKit\DataTransferObjects\Actions\Support\AuthKitRedirect;

/**
 * AuthKitActionResult
 *
 * Universal action outcome DTO for AuthKit application flows.
 *
 * Responsibilities:
 * - Provide a single standardized result contract for all package actions.
 * - Represent logical outcome state, HTTP-friendly status, and user-facing message.
 * - Carry flow progression information for multi-step authentication journeys.
 * - Carry optional redirect or navigation intent.
 * - Carry optional client-safe payload for JSON and frontend consumers.
 * - Carry optional internal-only payload for server-side transport concerns.
 * - Carry structured errors for domain, validation, or system failures.
 *
 * Design notes:
 * - This DTO is transport-agnostic and may be consumed by responders,
 *   controllers, tests, or other orchestration layers.
 * - Public payload is intended for safe serialization to clients.
 * - Internal payload is intended for server-side use only and should not be
 *   exposed in public responses unless explicitly intended.
 * - Flow state should be modeled through AuthKitFlowStep rather than a growing
 *   set of scattered booleans.
 *
 * Typical usage:
 * - Login action returns completed, two_factor_required, or
 *   email_verification_required.
 * - Password reset actions return token_required, completed, or failed.
 * - Email verification actions return notice, completed, or failed.
 */
final class AuthKitActionResult
{
    /**
     * Create a new universal action result instance.
     *
     * @param bool $ok
     * @param int $status
     * @param string $message
     * @param AuthKitFlowStep|null $flow
     * @param AuthKitRedirect|null $redirect
     * @param AuthKitPublicPayload|null $payload
     * @param AuthKitInternalPayload|null $internal
     * @param array<int, AuthKitError> $errors
     */
    public function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly string $message,
        public readonly ?AuthKitFlowStep $flow = null,
        public readonly ?AuthKitRedirect $redirect = null,
        public readonly ?AuthKitPublicPayload $payload = null,
        public readonly ?AuthKitInternalPayload $internal = null,
        public readonly array $errors = [],
    ) {}

    /**
     * Create a successful action result.
     *
     * @param string $message
     * @param int $status
     * @param AuthKitFlowStep|null $flow
     * @param AuthKitRedirect|null $redirect
     * @param AuthKitPublicPayload|null $payload
     * @param AuthKitInternalPayload|null $internal
     * @return self
     */
    public static function success(
        string $message = 'Operation completed.',
        int $status = 200,
        ?AuthKitFlowStep $flow = null,
        ?AuthKitRedirect $redirect = null,
        ?AuthKitPublicPayload $payload = null,
        ?AuthKitInternalPayload $internal = null,
    ): self {
        return new self(
            ok: true,
            status: $status,
            message: $message,
            flow: $flow,
            redirect: $redirect,
            payload: $payload,
            internal: $internal,
            errors: [],
        );
    }

    /**
     * Create a failed action result.
     *
     * @param string $message
     * @param int $status
     * @param AuthKitFlowStep|null $flow
     * @param array<int, AuthKitError> $errors
     * @param AuthKitRedirect|null $redirect
     * @param AuthKitPublicPayload|null $payload
     * @param AuthKitInternalPayload|null $internal
     * @return self
     */
    public static function failure(
        string $message = 'Operation failed.',
        int $status = 422,
        ?AuthKitFlowStep $flow = null,
        array $errors = [],
        ?AuthKitRedirect $redirect = null,
        ?AuthKitPublicPayload $payload = null,
        ?AuthKitInternalPayload $internal = null,
    ): self {
        return new self(
            ok: false,
            status: $status,
            message: $message,
            flow: $flow,
            redirect: $redirect,
            payload: $payload,
            internal: $internal,
            errors: $errors,
        );
    }

    /**
     * Create a validation failure result with grouped field messages.
     *
     * @param string $message
     * @param array<int, AuthKitError> $errors
     * @param array<string, array<int, string>> $fields
     * @param int $status
     * @param AuthKitFlowStep|null $flow
     * @param AuthKitRedirect|null $redirect
     * @param AuthKitInternalPayload|null $internal
     * @param array<string, mixed> $payload
     * @return self
     */
    public static function validationFailure(
        string $message = 'The given data was invalid.',
        array $errors = [],
        array $fields = [],
        int $status = 422,
        ?AuthKitFlowStep $flow = null,
        ?AuthKitRedirect $redirect = null,
        ?AuthKitInternalPayload $internal = null,
        array $payload = [],
    ): self {
        return new self(
            ok: false,
            status: $status,
            message: $message,
            flow: $flow ?? AuthKitFlowStep::failed(),
            redirect: $redirect,
            payload: AuthKitPublicPayload::withFields($fields, $payload),
            internal: $internal,
            errors: $errors,
        );
    }

    /**
     * Determine whether the result carries structured errors.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Determine whether the result carries redirect intent.
     *
     * @return bool
     */
    public function hasRedirect(): bool
    {
        return $this->redirect !== null;
    }

    /**
     * Determine whether the result carries public payload data.
     *
     * @return bool
     */
    public function hasPayload(): bool
    {
        return $this->payload !== null;
    }

    /**
     * Determine whether the result carries internal payload data.
     *
     * @return bool
     */
    public function hasInternal(): bool
    {
        return $this->internal !== null;
    }

    /**
     * Convert the result into a public normalized array representation.
     *
     * Intended for public responder and controller serialization boundaries.
     * Internal payload data is intentionally excluded.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'status' => $this->status,
            'message' => $this->message,
            'flow' => $this->flow?->toArray(),
            'redirect' => $this->redirect?->toArray(),
            'payload' => $this->payload?->toArray(),
            'errors' => array_map(
                static fn (AuthKitError $error): array => $error->toArray(),
                $this->errors
            ),
        ];
    }

    /**
     * Convert the result into a full normalized array representation.
     *
     * Intended for internal responder, controller, or test boundaries where
     * internal payload data must remain available.
     *
     * @return array<string, mixed>
     */
    public function toInternalArray(): array
    {
        return [
            'ok' => $this->ok,
            'status' => $this->status,
            'message' => $this->message,
            'flow' => $this->flow?->toArray(),
            'redirect' => $this->redirect?->toArray(),
            'payload' => $this->payload?->toArray(),
            'internal' => $this->internal?->toArray(),
            'errors' => array_map(
                static fn (AuthKitError $error): array => $error->toArray(),
                $this->errors
            ),
        ];
    }
}