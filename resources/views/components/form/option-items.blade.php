{{--
/**
 * Component: Form Option Items
 *
 * Recursive renderer for normalized select option items.
 *
 * Purpose:
 * - Renders flat <option> items.
 * - Renders grouped <optgroup> items recursively.
 * - Keeps complex option rendering logic out of the single-field renderer.
 *
 * Expected normalized shapes:
 *
 * Flat option:
 * [
 *   'value' => 'ng',
 *   'label' => 'Nigeria',
 *   'disabled' => false,
 *   'attributes' => [],
 * ]
 *
 * Grouped option:
 * [
 *   'label' => 'Africa',
 *   'options' => [
 *       ['value' => 'ng', 'label' => 'Nigeria'],
 *       ['value' => 'gh', 'label' => 'Ghana'],
 *   ],
 *   'disabled' => false,
 *   'attributes' => [],
 * ]
 *
 * Props:
 * - options: Normalized option array.
 * - selectedValues: Array of selected values normalized to strings.
 */
--}}

@props([
    'options' => [],
    'selectedValues' => [],
])

@php
    $resolvedOptions = is_array($options) ? $options : [];
    $resolvedSelectedValues = array_map(
        static fn ($value) => is_scalar($value) || $value === null ? (string) $value : '',
        is_array($selectedValues) ? $selectedValues : []
    );
@endphp

@foreach ($resolvedOptions as $option)
    @if (!is_array($option))
        @continue
    @endif

    @php
        $isGrouped = isset($option['label'], $option['options']) && is_array($option['options']);
    @endphp

    @if ($isGrouped)
        @php
            $groupLabel = (string) ($option['label'] ?? '');
            $groupDisabled = (bool) ($option['disabled'] ?? false);
            $groupAttributes = new \Illuminate\View\ComponentAttributeBag(
                is_array($option['attributes'] ?? null) ? $option['attributes'] : []
            );
            $groupOptions = is_array($option['options']) ? $option['options'] : [];
        @endphp

        <optgroup
                label="{{ $groupLabel }}"
                @disabled($groupDisabled)
                {{ $groupAttributes }}
        >
            <x-dynamic-component
                    :component="config('authkit.components.option_items', 'authkit::form.option-items')"
                    :options="$groupOptions"
                    :selected-values="$resolvedSelectedValues"
            />
        </optgroup>
    @else
        @php
            $optionValue = $option['value'] ?? null;
            $optionLabel = (string) ($option['label'] ?? '');
            $optionDisabled = (bool) ($option['disabled'] ?? false);
            $optionAttributes = new \Illuminate\View\ComponentAttributeBag(
                is_array($option['attributes'] ?? null) ? $option['attributes'] : []
            );

            $selected = in_array((string) $optionValue, $resolvedSelectedValues, true);
        @endphp

        <option
                value="{{ $optionValue }}"
                @selected($selected)
                @disabled($optionDisabled)
                {{ $optionAttributes }}
        >
            {{ $optionLabel }}
        </option>
    @endif
@endforeach