{{--
/**
 * Component: Settings Section
 *
 * Reusable section wrapper for authenticated AuthKit settings pages.
 *
 * Responsibilities:
 * - Provides a consistent section shell for settings/security content.
 * - Renders optional heading, description, and action areas.
 * - Exposes stable structural hooks for forms, summaries, and controls.
 *
 * Props:
 * - title: Section title text.
 * - description: Optional supporting description.
 * - as: Root element tag name.
 *
 * Slots:
 * - $slot: Main section body/content.
 * - actions: Optional right-aligned header actions.
 * - footer: Optional footer content rendered below the body.
 *
 * Notes:
 * - This component is intended for settings/security/session-related pages.
 * - Consumers may override this component to add richer UI treatments,
 *   badges, tabs, inline status chips, or custom layouts.
 */
--}}

@props([
    'title' => null,
    'description' => null,
    'as' => 'section',
])

@php
    $tag = is_string($as) && trim($as) !== '' ? trim($as) : 'section';

    $resolvedTitle = is_string($title) && trim($title) !== ''
        ? trim($title)
        : null;

    $resolvedDescription = is_string($description) && trim($description) !== ''
        ? trim($description)
        : null;

    $actions = $actions ?? null;
    $footer = $footer ?? null;

    $hasHeader = $resolvedTitle !== null || $resolvedDescription !== null || ($actions !== null && trim((string) $actions) !== '');
    $hasFooter = $footer !== null && trim((string) $footer) !== '';
@endphp

<{{ $tag }}
{{ $attributes->merge([
    'class' => 'authkit-settings-section',
    'data-authkit-settings-section' => '1',
]) }}
>
@if ($hasHeader)
    <div class="authkit-settings-section__header">
        <div class="authkit-settings-section__heading">
            @if ($resolvedTitle !== null)
                <h2 class="authkit-settings-section__title">
                    {{ $resolvedTitle }}
                </h2>
            @endif

            @if ($resolvedDescription !== null)
                <p class="authkit-settings-section__description">
                    {{ $resolvedDescription }}
                </p>
            @endif
        </div>

        @if ($actions !== null && trim((string) $actions) !== '')
            <div class="authkit-settings-section__actions">
                {{ $actions }}
            </div>
        @endif
    </div>
@endif

<div class="authkit-settings-section__body">
    {{ $slot }}
</div>

@if ($hasFooter)
    <div class="authkit-settings-section__footer">
        {{ $footer }}
    </div>
@endif
</{{ $tag }}>