<?php

namespace Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource\Schemas;

use Closure;
use Emmanpbarrameda\FilamentScaffold2\Database\TableInspector;
use Emmanpbarrameda\FilamentScaffold2\Integrations\ApiServiceGenerator;
use Emmanpbarrameda\FilamentScaffold2\Integrations\ShieldPolicyGenerator;
use Emmanpbarrameda\FilamentScaffold2\Support\Naming;
use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldRules;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/********************************************
 * ! Scaffold Manager form
 * ? Used by: ScaffoldResource::form()
 * ? detector: field names ("Table Name", "Model", "Table", ...) are the array keys
 *   GenerateScaffold reads — renaming a field breaks generation
 ********************************************/
final class ScaffoldForm
{
    /* =========================================
     * ! MARK: Layout (3 columns: info 2 + options 1, then full-width sections)
     * ========================================= */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::tableAndResourceSection(),
                self::generationOptionsSection(),
                self::tableStructureSection(),
                self::migrationFeaturesSection(),
            ])
            ->columns(3);
    }

    /* =========================================
     * ? MARK: Table & Resource Information
     * Table name, existing-table picker, model name, derived resource name
     * ? detector: typing a table name or picking a table fills Model + Resource;
     *   picking a table also loads its columns and unchecks Create Migration
     * ========================================= */
    protected static function tableAndResourceSection(): Section
    {
        return Section::make('Table & Resource Information')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('Table Name')
                            ->live(onBlur: true)
                            ->regex(ScaffoldRules::IDENTIFIER_REGEX)
                            ->validationMessages(['regex' => 'Use letters, numbers and underscores only (e.g. product_categories).'])
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (blank($state)) {
                                    return;
                                }
                                $modelName = Naming::modelNameFromTable($state);
                                $set('Model', 'app\\Models\\' . $modelName);
                                $set('Resource', Naming::expectedResourceLabel($modelName));
                            })
                            ->required(),

                        Select::make('Choose Table')
                            ->options(fn () => TableInspector::getAllTableNames())
                            ->searchable()
                            ->dehydrated(false)
                            ->live()
                            ->helperText('Pick an existing table to load its columns. Uncheck "Create Migration" in that case.')
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (! $state) {
                                    return;
                                }

                                $modelName = Naming::modelNameFromTable($state);
                                $set('Table Name', $state);
                                $set('Model', 'app\\Models\\' . $modelName);
                                $set('Resource', Naming::expectedResourceLabel($modelName));
                                $set('Table', TableInspector::getTableColumns($state));
                                $set('create_migration', false);
                            }),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('Model')
                            ->default('app\\Models\\')
                            ->required()
                            ->helperText('Models are always written to app/Models. Only the class name (last segment) is used.')
                            ->rule(function () {
                                return function (string $attribute, $value, Closure $fail) {
                                    $name = Naming::getFileName((string) $value);
                                    if (! preg_match(ScaffoldRules::MODEL_NAME_REGEX, $name)) {
                                        $fail('The model class name must be PascalCase (e.g. app\\Models\\ProductCategory).');
                                    }
                                };
                            })
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('Resource', Naming::expectedResourceLabel(Naming::getFileName((string) $state)));
                            }),

                        TextInput::make('Resource')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Derived from the model name by make:filament-resource.'),
                    ]),
            ])
            ->columnSpan(2);
    }

    /* =========================================
     * ? MARK: Generation Options
     * Which files/commands to run
     * ? detector: Create Policy / Create API are hidden when Shield / rupadana aren't installed
     * ========================================= */
    protected static function generationOptionsSection(): Section
    {
        return Section::make('Generation Options')
            ->schema([
                Checkbox::make('create_resource')
                    ->default(true)
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate Filament resource files (list, create, edit pages)'),

                Checkbox::make('create_model')
                    ->default(true)
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Create an Eloquent model for the table (skipped if the file already exists)'),

                Checkbox::make('simple_resource')
                    ->default(true)
                    ->label('Simple Resource')
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate modal-based forms instead of separate pages'),

                Checkbox::make('create_migration')
                    ->default(true)
                    ->live()
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Create migration file (only for new tables)'),

                Checkbox::make('create_factory')
                    ->default(false)
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate factory for test/demo data generation'),

                Checkbox::make('create_controller')
                    ->default(false)
                    ->live()
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Create traditional Laravel resource controller'),

                Checkbox::make('run_migrate')
                    ->default(true)
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Run the migration right away (needed for the resource to read the columns)')
                    ->hidden(fn (Get $get) => ! $get('create_migration')),

                Checkbox::make('create_route')
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Add a Route::resource() line to routes/web.php')
                    ->hidden(fn (Get $get) => ! $get('create_controller')),

                Checkbox::make('create_policy')
                    ->default(true)
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate policy + permissions with shield:generate and give them to super_admin (requires Filament Shield and Create Resource)')
                    ->hidden(fn () => ! ShieldPolicyGenerator::isAvailable()),

                Checkbox::make('create_api')
                    ->label('Create API')
                    ->default(true)
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate API endpoints (requires rupadana/filament-api-service)')
                    ->hidden(fn () => ! ApiServiceGenerator::isAvailable()),
            ])
            ->columns(2)
            ->columnSpan(1);
    }

    /* =========================================
     * ! MARK: Table Structure
     * The column repeater (one row per column)
     * ? detector: id/timestamps/deleted_at are rejected here — the migration writer adds them
     * ========================================= */
    protected static function tableStructureSection(): Section
    {
        return Section::make('Table Structure')
            ->schema([
                Repeater::make('Table')
                    ->minItems(1)
                    ->schema([
                        TextInput::make('name')
                            ->label('Field Name')
                            ->required()
                            ->regex(ScaffoldRules::IDENTIFIER_REGEX)
                            ->notIn(ScaffoldRules::RESERVED_COLUMNS)
                            ->validationMessages([
                                'regex' => 'Letters, numbers and underscores only.',
                                'not_in' => 'id and timestamp columns are added automatically.',
                            ]),
                        TextInput::make('translation'),
                        Select::make('type')
                            ->native(false)
                            ->searchable()
                            ->options(ScaffoldRules::columnTypes())
                            ->default('string')
                            ->required()
                            ->live(),
                        TextInput::make('enum_values')
                            ->label('Enum values')
                            ->placeholder('draft,published,archived')
                            ->helperText('Comma-separated')
                            ->required(fn (Get $get) => $get('type') === 'enum')
                            ->visible(fn (Get $get) => $get('type') === 'enum'),
                        Checkbox::make('nullable')
                            ->inline(false)
                            ->default(true),
                        Select::make('key')
                            ->options([
                                '' => 'NULL',
                                'unique' => 'Unique',
                                'index' => 'Index',
                            ])
                            ->default(''),
                        TextInput::make('default'),
                        Textarea::make('comment')
                            ->autosize(),
                    ])
                    ->columns(8),
            ])
            ->columnSpan('full');
    }

    /* =========================================
     * ? MARK: Migration Additional Features
     * Timestamps and soft deletes (soft_delete also adds SoftDeletes to the model + --soft-deletes to the resource)
     * ========================================= */
    protected static function migrationFeaturesSection(): Section
    {
        return Section::make('Migration Additional Features')
            ->schema([
                Checkbox::make('Created_at & Updated_at')
                    ->label('Created_at & Updated_at timestamps')
                    ->default(true)
                    ->inline(),
                Checkbox::make('soft_delete')
                    ->label('soft_delete (recycle bin)')
                    ->default(true)
                    ->inline(),
            ])
            ->columns(3)
            ->columnSpan('full');
    }
}
