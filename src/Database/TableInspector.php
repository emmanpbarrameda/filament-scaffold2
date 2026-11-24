<?php

namespace Emmanpbarrameda\FilamentScaffold2\Database;

use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldRules;
use Illuminate\Support\Facades\Schema as DbSchema;

/********************************************
 * ? Reads existing tables (the "Choose Table" select)
 * Uses Laravel's Schema builder, so it works on MySQL/MariaDB, SQLite and Postgres
 * (no raw SHOW TABLES / DESCRIBE)
 ********************************************/
final class TableInspector
{
    /* =========================================
     * ? MARK: Tables
     * ========================================= */

    /** @return array<string, string> table name => table name, sorted */
    public static function getAllTableNames(): array
    {
        $tables = collect(DbSchema::getTables());

        // ? detector: Laravel 12+ lists tables from every schema; keep only the current one
        if (method_exists(DbSchema::getFacadeRoot(), 'getCurrentSchemaName')) {
            $current = DbSchema::getCurrentSchemaName();
            if ($current !== null) {
                $tables = $tables->filter(fn (array $table) => ($table['schema'] ?? $current) === $current);
            }
        }

        $names = $tables->pluck('name')->sort()->values()->all();

        return array_combine($names, $names);
    }

    /* =========================================
     * ! MARK: Columns (fills the "Table" repeater)
     * ========================================= */

    /** Columns of an existing table, shaped like the form's "Table" repeater items. */
    public static function getTableColumns(string $tableName): array
    {
        // STEP 1: Guard — only valid identifiers of tables that exist
        if (! preg_match(ScaffoldRules::IDENTIFIER_REGEX, $tableName) || ! DbSchema::hasTable($tableName)) {
            return [];
        }

        // STEP 2: Single-column indexes → per-column key (unique wins over index)
        $keys = [];
        foreach (DbSchema::getIndexes($tableName) as $index) {
            if ($index['primary'] ?? false) {
                continue;
            }
            if (count($index['columns']) !== 1) {
                continue; // ? composite indexes can't be expressed per column
            }
            $column = $index['columns'][0];
            $keys[$column] = ($index['unique'] ?? false) ? 'unique' : ($keys[$column] ?? 'index');
        }

        // STEP 3: Columns → repeater rows (skip id/timestamps/soft delete and auto-increment)
        $columnDetails = [];

        foreach (DbSchema::getColumns($tableName) as $column) {
            if (in_array(strtolower($column['name']), ScaffoldRules::RESERVED_COLUMNS, true) || ($column['auto_increment'] ?? false)) {
                continue;
            }

            [$type, $enumValues] = self::mapDatabaseType($column['type_name'] ?? '', $column['type'] ?? '');

            $columnDetails[] = [
                'name' => $column['name'],
                'translation' => '',
                'type' => $type,
                'enum_values' => $enumValues,
                'nullable' => (bool) ($column['nullable'] ?? false),
                'key' => $keys[$column['name']] ?? '',
                'default' => self::cleanDefault($column['default'] ?? null),
                'comment' => $column['comment'] ?? '',
            ];
        }

        return $columnDetails;
    }

    /* =========================================
     * ? MARK: Type + default mapping
     * ========================================= */

    /**
     * Maps a driver type to a Blueprint type. Unknown types fall back to "string".
     *
     * @return array{0: string, 1: string} [blueprint type, comma-separated enum values]
     */
    public static function mapDatabaseType(string $typeName, string $fullType): array
    {
        $typeName = strtolower($typeName);
        $fullType = strtolower($fullType);

        // ? MySQL: enum('a','b') → read the values back from the full type
        if ($typeName === 'enum' && preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $fullType, $matches)) {
            return ['enum', implode(',', $matches[1])];
        }

        // ? MySQL: tinyint(1) is how booleans are stored
        if ($fullType === 'tinyint(1)') {
            return ['boolean', ''];
        }

        // ? MySQL / SQLite / Postgres type names
        $map = [
            'varchar' => 'string',
            'character varying' => 'string',
            'int' => 'integer',
            'integer' => 'integer',
            'int4' => 'integer',
            'bigint' => 'bigInteger',
            'int8' => 'bigInteger',
            'text' => 'text',
            'float' => 'float',
            'real' => 'double',
            'double' => 'double',
            'double precision' => 'double',
            'decimal' => 'decimal',
            'numeric' => 'decimal',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'dateTime',
            'timestamp' => 'timestamp',
            'timestamp without time zone' => 'timestamp',
            'char' => 'char',
            'character' => 'char',
            'bpchar' => 'char',
            'mediumtext' => 'mediumText',
            'longtext' => 'longText',
            'tinyint' => 'tinyInteger',
            'smallint' => 'smallInteger',
            'int2' => 'smallInteger',
            'mediumint' => 'mediumInteger',
            'json' => 'json',
            'jsonb' => 'jsonb',
            'binary' => 'binary',
            'blob' => 'binary',
            'bytea' => 'binary',
            'inet' => 'ipAddress',
            'macaddr' => 'macAddress',
        ];

        return [$map[$typeName] ?? 'string', ''];
    }

    /** Column default as plain text for the form ('' = no default). */
    public static function cleanDefault(mixed $default): string
    {
        if ($default === null) {
            return '';
        }

        $default = (string) $default;

        if (strtoupper($default) === 'NULL') {
            return '';
        }

        // ? Some drivers wrap defaults in quotes, e.g. 'draft' or 'draft'::character varying (Postgres)
        if (preg_match("/^'(.*)'(::[a-z ]+)?$/is", $default, $m)) {
            return $m[1];
        }

        return $default;
    }
}
