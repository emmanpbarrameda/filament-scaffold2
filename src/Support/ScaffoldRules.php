<?php

namespace Emmanpbarrameda\FilamentScaffold2\Support;

/********************************************
 * ! Shared rules for scaffold input
 * ? Used by: ScaffoldForm (client side) and ScaffoldValidator (server side)
 * ? detector: one source for both, so the form and the server can't drift apart
 ********************************************/
final class ScaffoldRules
{
    /* =========================================
     * ! MARK: Identifiers
     * ? detector: these values end up INSIDE generated PHP code — keep them strict
     * ========================================= */

    /** Table, column and model names. */
    public const IDENTIFIER_REGEX = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** Model class names must be PascalCase. */
    public const MODEL_NAME_REGEX = '/^[A-Z][A-Za-z0-9]*$/';

    /** Columns added automatically by the migration writer. */
    public const RESERVED_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** Allowed per-column key values ('' and null mean "no key"). */
    public const COLUMN_KEYS = ['', null, 'unique', 'index'];

    /* =========================================
     * ? MARK: Column types
     * ========================================= */

    /** Blueprint column types offered in the form (value => label). */
    public static function columnTypes(): array
    {
        return [
            'string' => 'string',
            'integer' => 'integer',
            'bigInteger' => 'bigInteger',
            'text' => 'text',
            'float' => 'float',
            'double' => 'double',
            'decimal' => 'decimal',
            'boolean' => 'boolean',
            'date' => 'date',
            'time' => 'time',
            'dateTime' => 'dateTime',
            'timestamp' => 'timestamp',
            'char' => 'char',
            'mediumText' => 'mediumText',
            'longText' => 'longText',
            'tinyInteger' => 'tinyInteger',
            'smallInteger' => 'smallInteger',
            'mediumInteger' => 'mediumInteger',
            'json' => 'json',
            'jsonb' => 'jsonb',
            'binary' => 'binary',
            'enum' => 'enum',
            'ipAddress' => 'ipAddress',
            'macAddress' => 'macAddress',
        ];
    }
}
