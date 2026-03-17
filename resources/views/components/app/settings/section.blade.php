{{--
/**
 * Component: Settings Section
 *
 * Reusable section wrapper for authenticated AuthKit settings pages.
 *
 * Purpose:
 * - Provide a consistent section shell for settings/security/session pages.
 * - Render an optional eyebrow, title, description, and actions area.
 * - Keep page templates thin and compositional.
 *
 * Props:
 * - title: Primary section title.
 * - description: Optional supporting copy.
 * - eyebrow: Optional small section label shown above the title.
 * - actions: Optional actions slot rendered in the header.
 *
 * Slots:
 * - default: Main section body content.
 * - actions: Optional header action controls.
 */
--}}

@props([
    'title' => '',
    'description' => null,
    'eyebrow' => null,
])

@php
    $resolvedTitle = is_string($title) ? trim($title) : '';
    $resolvedDescription = is_string($description) ? trim($description) : '';
    $resolvedEyebrow = is_string($eyebrow) ? trim($eyebrow) : '';

    $hasActions = isset($actions) && trim((string) $actions) !== '';
@endphp

<section {{ $attributes->merge(['class' => 'authkit-settings-section']) }}>
    @if ($resolvedEyebrow !== '' || $resolvedTitle !== '' || $resolvedDescription !== '' || $hasActions)
        <header class="authkit-settings-section__header">
            <div class="authkit-settings-section__header-main">
                @if ($resolvedEyebrow !== '')
                    <div class="authkit-settings-section__eyebrow">
                        {{ $resolvedEyebrow }}
                    </div>
                @endif

                @if ($resolvedTitle !== '')
                    <h2 class="authkit-settings-section__title">
                        {{ $resolvedTitle }}
                    </h2>
                @endif

                @if ($resolvedDescription !== '')
                    <p class="authkit-settings-section__description">
                        {{ $resolvedDescription }}
                    </p>
                @endif
            </div>

            @if ($hasActions)
                <div class="authkit-settings-section__actions">
                    {{ $actions }}
                </div>
            @endif
        </header>
    @endif

    <div class="authkit-settings-section__body">
        {{ $slot }}
    </div>
</section>