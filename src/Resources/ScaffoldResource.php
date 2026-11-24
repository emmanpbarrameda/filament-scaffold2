<?php

namespace Emmanpbarrameda\FilamentScaffold2\Resources;

use BackedEnum;
use Emmanpbarrameda\FilamentScaffold2\Actions\GenerateScaffold;
use Emmanpbarrameda\FilamentScaffold2\Database\TableInspector;
use Emmanpbarrameda\FilamentScaffold2\Generators\MigrationWriter;
use Emmanpbarrameda\FilamentScaffold2\Generators\ModelWriter;
use Emmanpbarrameda\FilamentScaffold2\Generators\RouteWriter;
use Emmanpbarrameda\FilamentScaffold2\Integrations\ApiServiceGenerator;
use Emmanpbarrameda\FilamentScaffold2\Integrations\ShieldPolicyGenerator;
use Emmanpbarrameda\FilamentScaffold2\Models\Scaffold;
use Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource\Pages;
use Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource\Schemas\ScaffoldForm;
use Emmanpbarrameda\FilamentScaffold2\Support\Naming;
use Emmanpbarrameda\FilamentScaffold2\Support\PhpExport;
use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldNotifier;
use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldRules;
use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldValidator;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/********************************************
 * ! Scaffold Manager resource (the panel page)
 * ? Only wires the resource together: navigation, access, form, pages
 * Delegates to: Schemas\ScaffoldForm        the form
 *               Actions\GenerateScaffold    the generation pipeline
 *               Generators\*                migration / model / route writers
 *               Database\TableInspector     reading existing tables
 *               Integrations\*              Filament Shield, rupadana/filament-api-service
 *               Support\*                   rules, validation, naming, notifications
 ********************************************/
class ScaffoldResource extends Resource
{
    protected static ?string $model = Scaffold::class;

    protected static ?string $pluralModelLabel = 'Scaffold';

    protected static ?string $modelLabel = 'Scaffold';

    protected static bool $shouldRegisterNavigation = true;

    /** @deprecated Use ScaffoldRules::IDENTIFIER_REGEX. */
    protected const IDENTIFIER_REGEX = ScaffoldRules::IDENTIFIER_REGEX;

    /** @deprecated Use ScaffoldRules::RESERVED_COLUMNS. */
    protected const RESERVED_COLUMNS = ScaffoldRules::RESERVED_COLUMNS;

    /* =========================================
     * ? MARK: Navigation (from config/filament-scaffold2.php)
     * ========================================= */
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-scaffold2.navigation.group', 'System');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return config('filament-scaffold2.navigation.icon', 'heroicon-o-cube-transparent');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-scaffold2.navigation.sort', 99);
    }

    public static function getNavigationLabel(): string|Htmlable
    {
        return config('filament-scaffold2.navigation.label', 'Scaffold Manager');
    }

    /* =========================================
     * ! MARK: Access
     * ? detector: second gate on top of the plugin's register() check,
     *   so the page is unreachable when FILAMENT_SCAFFOLD2_ENABLED is false
     * ========================================= */
    public static function canAccess(): bool
    {
        return (bool) config('filament-scaffold2.enabled', false) && parent::canAccess();
    }

    /* =========================================
     * ? MARK: Form + pages
     * ========================================= */
    public static function form(Schema $schema): Schema
    {
        return ScaffoldForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\CreateScaffold::route('/'),
        ];
    }

    /* =========================================
     * ? MARK: Backward-compatible delegators
     * Old static API, kept so existing callers/tests keep working
     * ! Remove before tagging v1.0.0
     * ========================================= */

    /** @deprecated Use ScaffoldRules::columnTypes(). */
    public static function columnTypes(): array
    {
        return ScaffoldRules::columnTypes();
    }

    /** @deprecated Use TableInspector::getAllTableNames(). */
    public static function getAllTableNames(): array
    {
        return TableInspector::getAllTableNames();
    }

    /** @deprecated Use TableInspector::getTableColumns(). */
    public static function getTableColumns(string $tableName): array
    {
        return TableInspector::getTableColumns($tableName);
    }

    /** @deprecated Use TableInspector::mapDatabaseType(). */
    public static function mapDatabaseType(string $typeName, string $fullType): array
    {
        return TableInspector::mapDatabaseType($typeName, $fullType);
    }

    /** @deprecated Use TableInspector::cleanDefault(). */
    protected static function cleanDefault(mixed $default): string
    {
        return TableInspector::cleanDefault($default);
    }

    /** @deprecated Use Naming::getFileName(). */
    public static function getFileName(?string $path): string
    {
        return Naming::getFileName($path);
    }

    /** @deprecated Use Naming::modelNameFromTable(). */
    public static function modelNameFromTable(string $table): string
    {
        return Naming::modelNameFromTable($table);
    }

    /** @deprecated Use Naming::expectedResourceLabel(). */
    protected static function expectedResourceLabel(string $modelName): string
    {
        return Naming::expectedResourceLabel($modelName);
    }

    /** @deprecated Use ScaffoldValidator::validateData(). */
    public static function validateData(array $data): void
    {
        ScaffoldValidator::validateData($data);
    }

    /** @deprecated Use ScaffoldValidator::parseEnumValues(). */
    public static function parseEnumValues(?string $values): array
    {
        return ScaffoldValidator::parseEnumValues($values);
    }

    /** @deprecated Use GenerateScaffold::handle(). */
    public static function generateFiles(array $data): void
    {
        GenerateScaffold::handle($data);
    }

    /** @deprecated Use MigrationWriter::buildMigration(). */
    public static function buildMigration(array $data): string
    {
        return MigrationWriter::buildMigration($data);
    }

    /** @deprecated Use MigrationWriter::generateColumnDefinition(). */
    public static function generateColumnDefinition(array $column): string
    {
        return MigrationWriter::generateColumnDefinition($column);
    }

    /** @deprecated Use PhpExport::list(). */
    protected static function exportList(array $values): string
    {
        return PhpExport::list($values);
    }

    /** @deprecated Use ModelWriter::buildModel(). */
    public static function buildModel(array $data): string
    {
        return ModelWriter::buildModel($data);
    }

    /** @deprecated Use RouteWriter::addRoutes(). */
    public static function addRoutes(string $tableName, string $modelName): void
    {
        RouteWriter::addRoutes($tableName, $modelName);
    }

    /** @deprecated Use ShieldPolicyGenerator::generateShieldPolicy(). */
    public static function generateShieldPolicy(string $modelName, string $panelId): ?string
    {
        return ShieldPolicyGenerator::generateShieldPolicy($modelName, $panelId);
    }

    /** @deprecated Use ShieldPolicyGenerator::shieldManualHint(). */
    protected static function shieldManualHint(string $resource, string $panelId, string $reason): string
    {
        return ShieldPolicyGenerator::shieldManualHint($resource, $panelId, $reason);
    }

    /** @deprecated Use ShieldPolicyGenerator::notifyPolicyCreated(). */
    protected static function notifyPolicyCreated(): void
    {
        ShieldPolicyGenerator::notifyPolicyCreated();
    }

    /** @deprecated Use ApiServiceGenerator::generateApiService(). */
    protected static function generateApiService(string $modelName, string $panelId): void
    {
        ApiServiceGenerator::generateApiService($modelName, $panelId);
    }

    /** @deprecated Use ApiServiceGenerator::findResourceFile(). */
    public static function findResourceFile(string $resourceClassName): ?string
    {
        return ApiServiceGenerator::findResourceFile($resourceClassName);
    }

    /** @deprecated Use GenerateScaffold::clearCaches(). */
    protected static function clearCaches(): void
    {
        GenerateScaffold::clearCaches();
    }

    /** @deprecated Use ScaffoldNotifier::notify(). */
    public static function notify(Notification $notification): void
    {
        ScaffoldNotifier::notify($notification);
    }
}
