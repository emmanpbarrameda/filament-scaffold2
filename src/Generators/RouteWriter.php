<?php

namespace Emmanpbarrameda\FilamentScaffold2\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/********************************************
 * ? Appends a Route::resource() line to routes/web.php
 * Only when "Create Controller" + "Create Route" are both checked
 ********************************************/
final class RouteWriter
{
    /* =========================================
     * ? MARK: Resource route
     * ========================================= */
    public static function addRoutes(string $tableName, string $modelName): void
    {
        $filePath = base_path('routes/web.php');

        if (! File::exists($filePath)) {
            return;
        }

        // STEP 1: kebab-case URI, controller by FQCN
        $uri = str_replace('_', '-', Str::snake($tableName));
        $line = "\nRoute::resource('{$uri}', \\App\\Http\\Controllers\\{$modelName}Controller::class)->only(['index', 'show']);\n";

        // STEP 2: Append once
        // ? detector: running the same scaffold twice must not add a duplicate route
        $content = File::get($filePath);
        if (! str_contains($content, "{$modelName}Controller::class")) {
            File::append($filePath, $line);
        }
    }
}
