<?php

namespace Emmanpbarrameda\FilamentScaffold2\Integrations;

use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldNotifier;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Throwable;

/********************************************
 * ? API endpoints via rupadana/filament-api-service (optional package)
 * Only runs when "Create API" is checked and the package is installed
 ********************************************/
final class ApiServiceGenerator
{
    public static function isAvailable(): bool
    {
        return class_exists(\Rupadana\ApiService\ApiService::class);
    }

    /* =========================================
     * ? MARK: make:filament-api-service
     * ========================================= */
    public static function generateApiService(string $modelName, string $panelId): void
    {
        $resourceClassName = "{$modelName}Resource";

        // STEP 1: The resource must exist (the API service is generated from it)
        if (! self::findResourceFile($resourceClassName)) {
            ScaffoldNotifier::notify(
                Notification::make()
                    ->danger()
                    ->title('Resource File Not Found')
                    ->body("Could not find {$resourceClassName}.php under app/Filament/Resources, so no API service was generated.")
            );

            return;
        }

        // STEP 2: Run the command and report the result
        try {
            Artisan::call('make:filament-api-service', [
                'resource' => $modelName,
                '--panel' => $panelId,
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();

            // ? detector: success is detected from the command's output text ("created"/"generated"), not its exit code
            $succeeded = str_contains($output, 'created') || str_contains($output, 'generated');

            ScaffoldNotifier::notify(
                Notification::make()
                    ->{$succeeded ? 'success' : 'warning'}()
                    ->persistent()
                    ->title($succeeded ? 'API Service Created Successfully!' : 'API Service Generation Issue')
                    ->body(new HtmlString(
                        ($succeeded ? 'API service has been generated for: ' : 'There was an issue generating the API service for: ')
                        . '<b>' . e($resourceClassName) . '</b><br><br><small><pre>' . e($output) . '</pre></small>'
                    ))
                    ->icon($succeeded ? 'heroicon-o-code-bracket' : 'heroicon-o-exclamation-triangle')
            );
        } catch (Throwable $e) {
            ScaffoldNotifier::notify(
                Notification::make()
                    ->danger()
                    ->title('API Service Generation Failed')
                    ->body('Unexpected error: ' . $e->getMessage())
            );
        }
    }

    /* =========================================
     * ? MARK: Helpers
     * ========================================= */

    /** Finds a resource class file in either the v4/v5 nested layout or the flat v3 layout. */
    public static function findResourceFile(string $resourceClassName): ?string
    {
        $directory = app_path('Filament/Resources');

        if (! File::isDirectory($directory)) {
            return null;
        }

        foreach (File::allFiles($directory) as $file) {
            if ($file->getFilename() === "{$resourceClassName}.php") {
                return $file->getPathname();
            }
        }

        return null;
    }
}
