{{--
/**
 * Component: App User Menu
 *
 * Lightweight authenticated user summary/menu trigger for the AuthKit app shell.
 *
 * Responsibilities:
 * - Displays the current authenticated user's identity summary.
 * - Provides a stable surface for profile/account actions.
 * - Exposes dedicated slots for consumer-defined menu content.
 *
 * Props:
 * - user: Optional authenticated user instance. Falls back to the configured guard user.
 * - guard: Guard name used to resolve the current user when "user" is not passed.
 * - title: Optional explicit display title.
 * - subtitle: Optional explicit secondary line.
 * - initials: Optional explicit initials fallback.
 *
 * Slots:
 * - $slot: Optional menu/action content rendered beneath the summary block.
 *
 * Notes:
 * - This component intentionally does not enforce dropdown behavior.
 * - Consumers may compose this into dropdowns, popovers, side panels,
 *   or static account summary sections.
 * - The displayed identity is resolved conservatively:
 *   name -> email -> configured login field -> "User".
 */
--}}

@props([
    'user' => null,
    'guard' => null,
    'title' => null,
    'subtitle' => null,
    'initials' => null,
    'pageKey' => null,
    'pageConfig' => [],
])

@php
    $resolvedGuard = is_string($guard) && trim($guard) !== ''
        ? trim($guard)
        : (string) config('authkit.auth.guard', 'web');

    $resolvedUser = $user ?: auth($resolvedGuard)->user();

    $identityField = (string) config('authkit.identity.login.field', 'email');

    $resolvedTitle = is_string($title) && trim($title) !== ''
        ? trim($title)
        : '';

    if ($resolvedTitle === '' && is_object($resolvedUser)) {
        $resolvedTitle = (string) (
            data_get($resolvedUser, 'name')
            ?: data_get($resolvedUser, 'email')
            ?: data_get($resolvedUser, $identityField)
            ?: 'User'
        );
    }

    if ($resolvedTitle === '') {
        $resolvedTitle = 'User';
    }

    $resolvedSubtitle = is_string($subtitle) && trim($subtitle) !== ''
        ? trim($subtitle)
        : '';

    if ($resolvedSubtitle === '' && is_object($resolvedUser)) {
        $email = (string) data_get($resolvedUser, 'email', '');
        $identityValue = (string) data_get($resolvedUser, $identityField, '');

        $resolvedSubtitle = $email !== ''
            ? $email
            : ($identityValue !== '' && $identityValue !== $resolvedTitle ? $identityValue : '');
    }

    $resolvedInitials = is_string($initials) && trim($initials) !== ''
        ? strtoupper(trim($initials))
        : '';

    if ($resolvedInitials === '') {
        $parts = preg_split('/\s+/', $resolvedTitle) ?: [];
        $letters = collect($parts)
            ->filter(fn ($part) => is_string($part) && $part !== '')
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        $resolvedInitials = $letters !== ''
            ? $letters
            : mb_strtoupper(mb_substr($resolvedTitle, 0, 1));
    }

    $hasMenuContent = trim((string) $slot) !== '';
@endphp

<div
        {{ $attributes->merge([
            'class' => 'authkit-app-user-menu',
            'data-authkit-app-user-menu' => '1',
        ]) }}
>
    <div class="authkit-app-user-menu__summary">
        <div class="authkit-app-user-menu__avatar" aria-hidden="true">
            {{ $resolvedInitials }}
        </div>

        <div class="authkit-app-user-menu__identity">
            <div class="authkit-app-user-menu__title">
                {{ $resolvedTitle }}
            </div>

            @if ($resolvedSubtitle !== '')
                <div class="authkit-app-user-menu__subtitle">
                    {{ $resolvedSubtitle }}
                </div>
            @endif
        </div>
    </div>

    @if ($hasMenuContent)
        <div class="authkit-app-user-menu__content">
            {{ $slot }}
        </div>
    @endif
</div>