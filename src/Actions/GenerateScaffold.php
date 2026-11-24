<?php

namespace Emmanpbarrameda\FilamentScaffold2\Actions;

use Emmanpbarrameda\FilamentScaffold2\Generators\MigrationWriter;
use Emmanpbarrameda\FilamentScaffold2\Generators\ModelWriter;
use Emmanpbarrameda\FilamentScaffold2\Generators\RouteWriter;
use Emmanpbarrameda\FilamentScaffold2\Integrations\ApiServiceGenerator;
use Emmanpbarrameda\FilamentScaffold2\Integrations\ShieldPolicyGenerator;
use Emmanpbarrameda\FilamentScaffold2\Support\Naming;
use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldNotifier;
use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldValidator;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\HtmlString;
use Throwable;

/********************************************
 * ! Scaffold pipeline (runs on "Create Scaffold")
 * ? Called by: Pages\CreateScaffold::handleRecordCreation()
 * Delegates to: ScaffoldValidator, MigrationWriter, ModelWriter, RouteWriter,
 *               ShieldPolicyGenerator, ApiServiceGenerator, ScaffoldNotifier
 ********************************************/
final class GenerateScaffold
{
    /* =========================================
     * ! MARK: Pipeline
     * ? detector: the ORDER of the steps matters
     *   - migration (+ migrate) and model must exist BEFORE make:filament-resource --generate,
     *     otherwise the generated form/table can't read the columns
     *   - caches are cleared BEFORE Shield, which runs in a fresh PHP process
     * ========================================= */
    public static function handle(array $data): void
    {
        // STEP 0: Some Artisan commands read the STDIN constant, which isn't defined in a web request
        if (! defined('STDIN')) {
            define('STDIN', fopen('php://stdin', 'r'));
        }

        // STEP 1: Server-side validation (mirrors the form rules), before anything is written
        ScaffoldValidator::validateData($data);

        $tableName = $data['Table Name'];
        $modelName = Naming::getFileName($data['Model']);
        $panelId = Filament::getCurrentPanel()?->getId() ?? Filament::getDefaultPanel()->getId();
        $warnings = [];

        // STEP 2: Migration (written in full, then migrated so --generate sees the columns)
        if ($data['create_migration'] ?? false) {
            $warnings[] = self::createMigration($data, $tableName);
        }

        // STEP 3: Model (must exist before make:filament-resource)
        if ($data['create_model'] ?? false) {
            $warnings[] = self::createModel($data, $modelName);
        }

        // STEP 4: Factory
        if ($data['create_factory'] ?? false) {
            self::createFactory($modelName);
        }

        // STEP 5: Filament resource
        if ($data['create_resource'] ?? false) {
            self::createResource($data, $modelName, $panelId);
        }

        // STEP 6: Controller + route
        if ($data['create_controller'] ?? false) {
            self::createController($modelName);

            if ($data['create_route'] ?? false) {
                RouteWriter::addRoutes($tableName, $modelName);
            }
        }

        // STEP 7: Clear caches in-process (Laravel + Filament panel components)
        // ? detector: before Shield, so its fresh boot discovers the new resource
        self::clearCaches();

        // STEP 8: Policy + permissions (Filament Shield, separate PHP process)
        if (($data['create_policy'] ?? false) && ($data['create_resource'] ?? false) && ShieldPolicyGenerator::isAvailable()) {
            $warnings[] = ShieldPolicyGenerator::generateShieldPolicy($modelName, $panelId);
        }

        // STEP 9: API service (rupadana/filament-api-service)
        if (($data['create_api'] ?? false) && ApiServiceGenerator::isAvailable()) {
            ApiServiceGenerator::generateApiService($modelName, $panelId);
        }

        // STEP 10: Final notification, with the warnings collected above (nulls = no warning)
        self::notifyDone($modelName, array_values(array_filter($warnings, fn ($warning) => $warning !== null)));
    }

    /* =========================================
     * ? MARK: Steps
     * ========================================= */

    /** @return string|null A warning when the table already exists. */
    private static function createMigration(array $data, string $tableName): ?string
    {
        if (DbSchema::hasTable($tableName)) {
            return "Table <b>{$tableName}</b> already exists, so no migration was created.";
        }

        $migrationPath = database_path('migrations/' . date('Y_m_d_His') . "_create_{$tableName}_table.php");
        File::put($migrationPath, MigrationWriter::buildMigration($data));

        if ($data['run_migrate'] ?? false) {
            Artisan::call('migrate', ['--no-interaction' => true]);
        }

        return null;
    }

    /** @return string|null A warning when the model file already exists (it is never overwritten). */
    private static function createModel(array $data, string $modelName): ?string
    {
        $modelPath = app_path("Models/{$modelName}.php");

        if (File::exists($modelPath)) {
            return "Model <b>{$modelName}</b> already exists and was not overwritten.";
        }

        File::ensureDirectoryExists(dirname($modelPath));
        File::put($modelPath, ModelWriter::buildModel($data));

        return null;
    }

    private static function createFactory(string $modelName): void
    {
        Artisan::call('make:factory', [
            'name' => "{$modelName}Factory",
            '--model' => $modelName,
            '--no-interaction' => true,
        ]);
    }

    /*
     * --generate builds the form & table from the DB columns,
     * in the v4/v5 layout (Resource + Schemas/ + Tables/ + Pages/).
     * ? detector: a failure here is logged and rethrown, so the page shows "Scaffold failed"
     */
    private static function createResource(array $data, string $modelName, string $panelId): void
    {
        $options = [
            'model' => $modelName,
            '--generate' => true,
            '--force' => true,
            '--panel' => $panelId,
            '--no-interaction' => true,
        ];

        if ($data['simple_resource'] ?? false) {
            $options['--simple'] = true;
        }

        if ($data['soft_delete'] ?? false) {
            $options['--soft-deletes'] = true;
        }

        try {
            Artisan::call('make:filament-resource', $options);
            Log::info('[filament-scaffold2] make:filament-resource output', ['output' => Artisan::output()]);
        } catch (Throwable $e) {
            Log::error('[filament-scaffold2] Resource generation failed', ['exception' => $e]);

            throw $e;
        }
    }

    private static function createController(string $modelName): void
    {
        Artisan::call('make:controller', [
            'name' => "{$modelName}Controller",
            '--model' => $modelName,
            '--resource' => true,
            '--no-interaction' => true,
        ]);
    }

    /* =========================================
     * ? MARK: Helpers
     * ========================================= */

    /*
     * Clears Laravel's caches AND Filament's cached panel components.
     * ! detector: the panel cache must be cleared EXPLICITLY — Filament only hooks filament:optimize-clear
     *   into optimize:clear when runningInConsole(), and this runs in a WEB request. Without it, a panel
     *   cached by `php artisan optimize` / filament:cache-components never discovers the new resource
     *   (missing from the sidebar and the Shield Roles page).
     * ? detector: filament:clear-cached-components, not filament:optimize-clear — the latter also runs
     *   icons:clear, which blade-icons only registers in the console ("no commands in the icons namespace")
     * ? detector: never fatal; a failed clear only means a manual `php artisan optimize:clear` is needed
     */
    public static function clearCaches(): void
    {
        foreach (['optimize:clear', 'filament:clear-cached-components'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable $e) {
                Log::warning("[filament-scaffold2] {$command} failed", ['message' => $e->getMessage()]);
            }
        }
    }

    /** @param  array<string>  $warnings */
    private static function notifyDone(string $modelName, array $warnings): void
    {
        $body = 'The scaffold for <b>' . e($modelName) . '</b> has been created.';
        if ($warnings !== []) {
            $body .= '<br><br>' . implode('<br>', $warnings);
        }

        ScaffoldNotifier::notify(
            Notification::make()
                ->success()
                ->persistent()
                ->title('Scaffold created')
                ->body(new HtmlString($body))
                ->icon('heroicon-o-cube-transparent')
        );
    }
}
