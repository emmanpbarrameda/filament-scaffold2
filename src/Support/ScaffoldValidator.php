<?php

namespace Emmanpbarrameda\FilamentScaffold2\Support;

use InvalidArgumentException;

/********************************************
 * ! Server-side validation (mirrors the form rules)
 * ? Runs before anything is written, because these values end up inside generated PHP files
 * ? detector: the form can be bypassed (direct Livewire calls), so never rely on it alone
 ********************************************/
final class ScaffoldValidator
{
    /* =========================================
     * ! MARK: Validate scaffold data
     * ========================================= */

    /** @throws InvalidArgumentException */
    public static function validateData(array $data): void
    {
        // STEP 1: Table name
        $table = (string) ($data['Table Name'] ?? '');
        if (! preg_match(ScaffoldRules::IDENTIFIER_REGEX, $table)) {
            throw new InvalidArgumentException("Invalid table name [{$table}].");
        }

        // STEP 2: Model class name (last segment of the "Model" input)
        $model = Naming::getFileName($data['Model'] ?? '');
        if (! preg_match(ScaffoldRules::MODEL_NAME_REGEX, $model)) {
            throw new InvalidArgumentException("Invalid model name [{$model}].");
        }

        // STEP 3: Each column — name, type, key, enum values
        $types = array_keys(ScaffoldRules::columnTypes());

        foreach ($data['Table'] ?? [] as $column) {
            $name = (string) ($column['name'] ?? '');
            if (! preg_match(ScaffoldRules::IDENTIFIER_REGEX, $name) || in_array(strtolower($name), ScaffoldRules::RESERVED_COLUMNS, true)) {
                throw new InvalidArgumentException("Invalid column name [{$name}].");
            }

            if (! in_array($column['type'] ?? '', $types, true)) {
                throw new InvalidArgumentException("Invalid column type for [{$name}].");
            }

            if (! in_array($column['key'] ?? '', ScaffoldRules::COLUMN_KEYS, true)) {
                throw new InvalidArgumentException("Invalid key for [{$name}].");
            }

            if (($column['type'] ?? '') === 'enum' && self::parseEnumValues($column['enum_values'] ?? '') === []) {
                throw new InvalidArgumentException("Enum column [{$name}] needs at least one value.");
            }
        }
    }

    /* =========================================
     * ? MARK: Helpers
     * ========================================= */

    /** "a, b,,a" → ['a', 'b'] (trimmed, no blanks, no duplicates) */
    public static function parseEnumValues(?string $values): array
    {
        return collect(explode(',', (string) $values))
            ->map(fn ($value) => trim($value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}
