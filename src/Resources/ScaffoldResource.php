<?php

namespace Emmanpbarrameda\FilamentScaffold2\Resources;

use BackedEnum;
use Emmanpbarrameda\FilamentScaffold2\Models\Scaffold;
use Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource\Pages;
use Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource\Schemas\ScaffoldForm;
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
}
