<?php

namespace Xul\AuthKit\Support\Resolvers;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use UnitEnum;
use Xul\AuthKit\Contracts\Forms\FieldOptionsProviderContract;
use Xul\AuthKit\Contracts\Forms\FieldOptionsResolverContract;

final class FieldOptionsResolver implements FieldOptionsResolverContract
{
    /**
     * Resolve normalized options for a field.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $field, array $context = []): array
    {
        $type = (string) ($field['type'] ?? 'text');

        if (!$this->supportsOptions($type)) {
            return [];
        }

        $options = $field['options'] ?? [];

        if (!is_array($options) || $options === []) {
            return [];
        }

        $source = mb_strtolower(trim((string) ($options['source'] ?? 'array')));

        return match ($source) {
            'array' => $this->resolveFromArray($options),
            'enum' => $this->resolveFromEnum($options),
            'class' => $this->resolveFromClass($field, $options, $context),
            'model' => $this->resolveFromModel($options),
            default => [],
        };
    }

    protected function supportsOptions(string $type): bool
    {
        return in_array($type, ['select', 'multiselect', 'radio_group', 'checkbox_group', 'radio'], true);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    protected function resolveFromArray(array $options): array
    {
        $items = $options['items'] ?? [];

        if (!is_array($items)) {
            return [];
        }

        return $this->normalizeOptionItems($items);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    protected function resolveFromEnum(array $options): array
    {
        $class = $options['class'] ?? null;

        if (!is_string($class) || $class === '' || !enum_exists($class)) {
            return [];
        }

        $items = [];

        foreach ($class::cases() as $case) {
            $value = $case instanceof BackedEnum ? $case->value : $case->name;
            $label = $this->enumCaseLabel($case);

            $items[] = [
                'value' => $value,
                'label' => $label,
                'disabled' => false,
                'attributes' => [],
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    protected function resolveFromClass(array $field, array $options, array $context = []): array
    {
        $class = $options['class'] ?? null;

        if (!is_string($class) || $class === '' || !class_exists($class)) {
            return [];
        }

        $provider = app($class);

        if (!$provider instanceof FieldOptionsProviderContract) {
            return [];
        }

        $items = $provider->resolve($field, $context);

        return is_array($items) ? $this->normalizeOptionItems($items) : [];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    protected function resolveFromModel(array $options): array
    {
        $modelClass = $options['model'] ?? null;

        if (!is_string($modelClass) || $modelClass === '' || !class_exists($modelClass)) {
            return [];
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        $labelBy = $this->stringOrDefault($options['label_by'] ?? null, 'name');
        $valueBy = $this->stringOrDefault($options['value_by'] ?? null, 'id');
        $orderBy = $this->nullableString($options['order_by'] ?? null);
        $where = is_array($options['where'] ?? null) ? $options['where'] : [];

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();
        $query = $modelClass::query();

        foreach ($where as $clause) {
            if (is_array($clause) && count($clause) >= 3) {
                $query->where($clause[0], $clause[1], $clause[2]);
            }
        }

        if ($orderBy !== null) {
            $query->orderBy($orderBy);
        }

        /** @var Collection<int, Model> $rows */
        $rows = $query->get();

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'value' => data_get($row, $valueBy),
                'label' => (string) data_get($row, $labelBy, ''),
                'disabled' => false,
                'attributes' => [],
            ];
        }

        return $this->normalizeOptionItems($items);
    }

    /**
     * @param  UnitEnum  $case
     */
    protected function enumCaseLabel(UnitEnum $case): string
    {
        if (method_exists($case, 'label')) {
            $label = $case->label();

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        if (method_exists($case, 'getLabel')) {
            $label = $case->getLabel();

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        $name = str_replace(['_', '-'], ' ', $case->name);

        return ucwords(trim($name));
    }

    /**
     * Normalize option items into a standard shape.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeOptionItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Grouped options
            if (isset($item['label'], $item['options']) && is_array($item['options'])) {
                $normalized[] = [
                    'label' => (string) $item['label'],
                    'options' => $this->normalizeOptionItems($item['options']),
                ];

                continue;
            }

            $normalized[] = [
                'value' => $item['value'] ?? null,
                'label' => (string) ($item['label'] ?? ''),
                'disabled' => $this->bool($item['disabled'] ?? false),
                'attributes' => is_array($item['attributes'] ?? null) ? $item['attributes'] : [],
            ];
        }

        return $normalized;
    }

    protected function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    protected function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    protected function stringOrDefault(mixed $value, string $default): string
    {
        $value = $this->nullableString($value);

        return $value ?? $default;
    }
}