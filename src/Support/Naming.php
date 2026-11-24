<?php

namespace Emmanpbarrameda\FilamentScaffold2\Support;

use Illuminate\Support\Str;

/********************************************
 * ? Name helpers
 * Turns user input (paths, table names) into class names
 ********************************************/
final class Naming
{
    /*
     * Last segment of a class path, without ".php".
     * ? detector: accepts both / and \ — basename() doesn't split backslashes on Linux/macOS
     */
    public static function getFileName(?string $path): string
    {
        $path = str_replace('/', '\\', (string) $path);
        $name = Str::afterLast($path, '\\');

        return Str::beforeLast($name, '.php');
    }

    /** product_categories → ProductCategory */
    public static function modelNameFromTable(string $table): string
    {
        return Str::studly(Str::singular($table));
    }

    /** The resource class make:filament-resource will create (v4/v5 nested layout), shown in the form. */
    public static function expectedResourceLabel(string $modelName): string
    {
        if ($modelName === '') {
            return '';
        }

        return 'app\\Filament\\Resources\\' . Str::plural($modelName) . '\\' . $modelName . 'Resource';
    }
}
