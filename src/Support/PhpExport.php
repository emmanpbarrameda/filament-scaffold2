<?php

namespace Emmanpbarrameda\FilamentScaffold2\Support;

/********************************************
 * ? Safe PHP literals for generated code
 * ? detector: var_export() escapes quotes and backslashes, so user input can't break the file
 ********************************************/
final class PhpExport
{
    /** ['a', "it's"] → "['a', 'it\'s']" */
    public static function list(array $values): string
    {
        return '[' . implode(', ', array_map(fn ($value) => var_export((string) $value, true), $values)) . ']';
    }
}
