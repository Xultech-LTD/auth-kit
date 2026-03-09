{{--
/**
 * Component: Theme Toggle
 *
 * Reusable appearance-mode toggle for AuthKit pages.
 *
 * Purpose:
 * - Allows users to switch between light, dark, and optionally system mode.
 * - Exposes stable semantic classes and data attributes for package JavaScript.
 * - Can be placed anywhere in the UI: headers, cards, navbars, or page corners.
 *
 * Supported variants:
 * - buttons  : Renders one button per mode option.
 * - dropdown : Renders a select dropdown.
 * - icon     : Renders a compact single-button cycle trigger.
 * - auto     : Resolves to the configured default variant.
 *
 * Notes:
 * - This component does not hardcode visual styling beyond semantic classes.
 * - Package JavaScript is responsible for applying the selected mode and persisting it.
 * - Consumers may publish and override this component freely.
 *
 * Props:
 * - variant: auto|buttons|dropdown|icon
 * - allowSystem: Whether to include the "system" option.
 * - showLabels: Whether labels should be shown alongside icons where applicable.
 * - unstyled: When true, suppresses package classes where possible.
 */
--}}

@props([
    'variant' => 'auto',
    'allowSystem' => null,
    'showLabels' => null,
    'unstyled' => false,
])

@php
    $toggleConfig = (array) config('authkit.ui.toggle', []);

    $configuredVariant = (string) data_get($toggleConfig, 'variant', 'buttons');
    $resolvedVariant = $variant === 'auto' ? $configuredVariant : (string) $variant;
    $resolvedVariant = $resolvedVariant !== '' ? $resolvedVariant : 'buttons';

    $resolvedAllowSystem = is_bool($allowSystem)
        ? $allowSystem
        : (bool) data_get($toggleConfig, 'allow_system', true);

    $resolvedShowLabels = is_bool($showLabels)
        ? $showLabels
        : (bool) data_get($toggleConfig, 'show_labels', true);

    $toggleAttribute = (string) data_get($toggleConfig, 'attribute', 'data-authkit-theme-toggle');

    $modes = [
        'light' => [
            'label' => 'Light',
            'icon' => '☀',
        ],
        'dark' => [
            'label' => 'Dark',
            'icon' => '🌙',
        ],
    ];

    if ($resolvedAllowSystem) {
        $modes['system'] = [
            'label' => 'System',
            'icon' => '◐',
        ];
    }

    $wrapperClass = $unstyled ? '' : 'authkit-theme-toggle';
    $variantClass = $unstyled ? '' : 'authkit-theme-toggle--'.$resolvedVariant;
    $buttonClass = $unstyled ? '' : 'authkit-theme-toggle__button';
    $buttonLabelClass = $unstyled ? '' : 'authkit-theme-toggle__label';
    $buttonIconClass = $unstyled ? '' : 'authkit-theme-toggle__icon';
    $selectClass = $unstyled ? '' : 'authkit-theme-toggle__select';
    $iconButtonClass = $unstyled ? '' : 'authkit-theme-toggle__icon-button';
@endphp

<div
        {{ $attributes->merge([
            'class' => trim($wrapperClass.' '.$variantClass),
        ]) }}
>
    @if ($resolvedVariant === 'dropdown')
        <label class="{{ $unstyled ? '' : 'authkit-theme-toggle__dropdown-wrapper' }}">
            <select
                    class="{{ $selectClass }}"
                    data-authkit-theme-toggle-select="1"
                    aria-label="Select appearance mode"
            >
                @foreach ($modes as $mode => $meta)
                    <option value="{{ $mode }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </label>
    @elseif ($resolvedVariant === 'icon')
        <button
                type="button"
                class="{{ $iconButtonClass }}"
                data-authkit-theme-toggle-cycle="1"
                aria-label="Toggle appearance mode"
                title="Toggle appearance mode"
        >
            <span class="{{ $buttonIconClass }}" aria-hidden="true">◐</span>

            @if ($resolvedShowLabels)
                <span class="{{ $buttonLabelClass }}">Theme</span>
            @endif
        </button>
    @else
        @foreach ($modes as $mode => $meta)
            <button
                    type="button"
                    class="{{ $buttonClass }}"
            {{ $toggleAttribute }}="{{ $mode }}"
            data-authkit-theme-toggle-option="{{ $mode }}"
            aria-label="Switch to {{ strtolower($meta['label']) }} mode"
            >
            <span class="{{ $buttonIconClass }}" aria-hidden="true">{{ $meta['icon'] }}</span>

            @if ($resolvedShowLabels)
                <span class="{{ $buttonLabelClass }}">{{ $meta['label'] }}</span>
                @endif
                </button>
                @endforeach
            @endif
</div>