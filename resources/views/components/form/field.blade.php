{{--
/**
* Component: Form Field
*
* Generic schema-driven field renderer for a single normalized AuthKit field.
*
* Purpose:
* - Renders one resolved field definition using the configured primitive component.
* - Centralizes field presentation logic so page templates remain focused on
*   overall page composition rather than field-type-specific rendering rules.
*
* Responsibilities:
* - Render hidden fields without visible wrapper/label/help/error UI.
* - Render checkbox fields using the checkbox component with slot-based label text.
* - Render visible fields with optional wrapper, label, control, help text,
*   and inline validation feedback.
* - Pass normalized field metadata into the resolved primitive component.
*
* Expected field shape:
* - name
* - label
* - type
* - required
* - placeholder
* - help
* - autocomplete
* - inputmode
* - value
* - checked
* - multiple
* - rows
* - accept
* - options
* - attributes
* - wrapper
* - component
* - render
*
* Notes:
* - Hidden fields are always rendered as controls because they may carry request
*   context required for form submission, even when "render" is false.
* - Checkbox fields use their own rendering path because the checkbox component
*   accepts label content via slot rather than a separate label element.
* - Option-bearing fields such as select/multiselect receive normalized options.
*
* Temporary safety note:
* - Arbitrary attribute bag spreading into <x-dynamic-component> has been
*   intentionally disabled here because it can produce invalid compiled Blade/PHP
*   in package test environments.
* - Once rendering is stable, extra attributes can be reintroduced through a
*   safer explicit-prop strategy.
*
* Props:
* - field: Normalized field definition array.
* - unstyled: When true, passes unstyled mode down to child components where relevant.
*/
--}}

@props([
    'field',
    'unstyled' => false,
])

@php
    /**
     * Component aliases from configuration.
     */
    $components = (array) config('authkit.components', []);

    /**
     * Normalized field values.
     */
    $name = (string) ($field['name'] ?? '');
    $label = (string) ($field['label'] ?? '');
    $type = (string) ($field['type'] ?? 'text');
    $required = (bool) ($field['required'] ?? false);
    $placeholder = $field['placeholder'] ?? null;
    $help = $field['help'] ?? null;
    $autocomplete = $field['autocomplete'] ?? null;
    $inputmode = $field['inputmode'] ?? null;
    $value = $field['value'] ?? null;
    $checked = (bool) ($field['checked'] ?? false);
    $multiple = (bool) ($field['multiple'] ?? false);
    $rows = $field['rows'] ?? 4;
    $accept = $field['accept'] ?? null;
    $options = is_array($field['options'] ?? null) ? $field['options'] : [];
    $wrapper = is_array($field['wrapper'] ?? null) ? $field['wrapper'] : [];

    /**
     * Resolved primitive/component aliases.
     */
    $component = (string) ($field['component'] ?? ($components['input'] ?? 'authkit::form.input'));
    $render = array_key_exists('render', $field) ? (bool) $field['render'] : true;

    /**
     * Basic DOM identity.
     */
    $id = $name !== '' ? $name : null;

    /**
     * Wrapper attributes are kept because they are rendered on a normal HTML node,
     * not on a Blade component tag.
     *
     * Always apply the base visible-field wrapper class so CSS has a stable hook,
     * then append any configured wrapper class.
     */
    $customWrapperClass = is_string($wrapper['class'] ?? null) ? trim((string) $wrapper['class']) : '';
    $wrapperClass = $customWrapperClass !== ''
        ? $customWrapperClass
        : 'authkit-field';
    $wrapperStyle = is_string($wrapper['style'] ?? null) ? trim((string) $wrapper['style']) : '';

    $wrapperAttributes = [];
    if ($wrapperClass !== '') {
        $wrapperAttributes['class'] = $wrapperClass;
    }
    if ($wrapperStyle !== '') {
        $wrapperAttributes['style'] = $wrapperStyle;
    }

    /**
     * Supporting component aliases.
     */
    $labelComponent = (string) ($components['label'] ?? 'authkit::form.label');
    $helpComponent = (string) ($components['help'] ?? 'authkit::form.help');
    $errorComponent = (string) ($components['error'] ?? 'authkit::form.error');

    /**
     * Field-type helpers.
     */
    $isHidden = $type === 'hidden';
    $isCheckbox = $type === 'checkbox';
    $isTextarea = $type === 'textarea';
    $isSelectLike = in_array($type, ['select', 'multiselect'], true);
    $isOtp = $type === 'otp';

    /**
     * Selection resolution for select/multiselect controls.
     */
    $selectedValues = $multiple
        ? (array) old($name, is_array($value) ? $value : [])
        : [old($name, $value)];
@endphp

{{-- Do not render anything when the field has no usable name. --}}
@if ($name === '')
    @php return; @endphp
@endif

{{-- Hidden fields are rendered without wrapper, label, help, or error UI. --}}
@if ($isHidden)
    <x-dynamic-component
            :component="$component"
            :name="$name"
            :id="$id"
            type="hidden"
            :value="$value"
            :unstyled="$unstyled"
    />
    @php return; @endphp
@endif

{{-- Respect explicit render=false for non-hidden fields. --}}
@if (! $render)
    @php return; @endphp
@endif

<div {{ (new \Illuminate\View\ComponentAttributeBag($wrapperAttributes)) }}>
    {{-- Checkbox fields render through their own path because label text is slot-based. --}}
    @if ($isCheckbox)
        <x-dynamic-component
                :component="$component"
                :name="$name"
                :id="$id"
                :checked="$checked"
                :unstyled="$unstyled"
        >
            {{ $label }}
        </x-dynamic-component>

        <x-dynamic-component
                :component="$errorComponent"
                :name="$name"
                :unstyled="$unstyled"
        />

    @else
        {{-- Render label only when present. --}}
        @if ($label !== '')
            <x-dynamic-component
                    :component="$labelComponent"
                    :for="$id"
                    :unstyled="$unstyled"
            >
                {{ $label }}
            </x-dynamic-component>
        @endif

        {{-- Render the appropriate control primitive by normalized field type. --}}
        @if ($isTextarea)
            <x-dynamic-component
                    :component="$component"
                    :name="$name"
                    :id="$id"
                    :value="$value"
                    :rows="$rows"
                    :placeholder="$placeholder"
                    :autocomplete="$autocomplete"
                    :required="$required"
                    :unstyled="$unstyled"
            />

        @elseif ($isSelectLike)
            <x-dynamic-component
                    :component="$component"
                    :name="$name"
                    :id="$id"
                    :value="$value"
                    :multiple="$multiple"
                    :required="$required"
                    :unstyled="$unstyled"
            >
                @foreach ($options as $option)
                    @if (isset($option['label'], $option['options']) && is_array($option['options']))
                        <optgroup label="{{ (string) $option['label'] }}">
                            @foreach ($option['options'] as $grouped)
                                @php
                                    $optionValue = $grouped['value'] ?? null;
                                    $optionLabel = (string) ($grouped['label'] ?? '');
                                    $optionDisabled = (bool) ($grouped['disabled'] ?? false);
                                    $optionAttributes = new \Illuminate\View\ComponentAttributeBag(
                                        is_array($grouped['attributes'] ?? null) ? $grouped['attributes'] : []
                                    );
                                @endphp

                                <option
                                        value="{{ $optionValue }}"
                                        @selected(in_array($optionValue, $selectedValues, true))
                                        @disabled($optionDisabled)
                                        {{ $optionAttributes }}
                                >
                                    {{ $optionLabel }}
                                </option>
                            @endforeach
                        </optgroup>
                    @else
                        @php
                            $optionValue = $option['value'] ?? null;
                            $optionLabel = (string) ($option['label'] ?? '');
                            $optionDisabled = (bool) ($option['disabled'] ?? false);
                            $optionAttributes = new \Illuminate\View\ComponentAttributeBag(
                                is_array($option['attributes'] ?? null) ? $option['attributes'] : []
                            );
                        @endphp

                        <option
                                value="{{ $optionValue }}"
                                @selected(in_array($optionValue, $selectedValues, true))
                                @disabled($optionDisabled)
                                {{ $optionAttributes }}
                        >
                            {{ $optionLabel }}
                        </option>
                    @endif
                @endforeach
            </x-dynamic-component>

        @elseif ($isOtp)
            <x-dynamic-component
                    :component="$component"
                    :name="$name"
                    :id="$id"
                    :value="$value"
                    :placeholder="$placeholder"
                    :autocomplete="$autocomplete"
                    :inputmode="$inputmode"
                    :required="$required"
                    :unstyled="$unstyled"
            />

        @else
            <x-dynamic-component
                    :component="$component"
                    :name="$name"
                    :id="$id"
                    :type="$type"
                    :value="$value"
                    :placeholder="$placeholder"
                    :autocomplete="$autocomplete"
                    :inputmode="$inputmode"
                    :accept="$accept"
                    :required="$required"
                    :unstyled="$unstyled"
            />
        @endif

        {{-- Optional help text rendered beneath the control. --}}
        @if (is_string($help) && $help !== '')
            <x-dynamic-component
                    :component="$helpComponent"
                    :unstyled="$unstyled"
            >
                {{ $help }}
            </x-dynamic-component>
        @endif

        {{-- Inline validation feedback for visible non-checkbox controls. --}}
        <x-dynamic-component
                :component="$errorComponent"
                :name="$name"
                :unstyled="$unstyled"
        />
    @endif
</div>
