<?php

namespace Emmanpbarrameda\FilamentScaffold2\Resources;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Emmanpbarrameda\FilamentScaffold2\Models\Scaffold;
use Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource\Pages;
use Throwable;
use UnitEnum;

class ScaffoldResource extends Resource
{
    protected static ?string $model = Scaffold::class;

    protected static ?string $pluralModelLabel = 'Scaffold';

    protected static ?string $modelLabel = 'Scaffold';

    protected static bool $shouldRegisterNavigation = true;

    /** Identifiers that end up inside generated PHP code (table, column, model names). */
    protected const IDENTIFIER_REGEX = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** Columns added automatically by the migration writer. */
    protected const RESERVED_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /********************************************
     * NAVIGATION (from config/filament-scaffold2.php)
     ********************************************/
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

    /********************************************
     * ACCESS: second gate on top of the plugin check,
     * so the page is unreachable when disabled.
     ********************************************/
    public static function canAccess(): bool
    {
        return (bool) config('filament-scaffold2.enabled', false) && parent::canAccess();
    }

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                /********************************************
                 * TABLE NAME, MODEL NAME, RESOURCE NAME
                 */
                Section::make('Table & Resource Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('Table Name')
                                    ->live(onBlur: true)
                                    ->regex(self::IDENTIFIER_REGEX)
                                    ->validationMessages(['regex' => 'Use letters, numbers and underscores only (e.g. product_categories).'])
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if (blank($state)) {
                                            return;
                                        }
                                        $modelName = self::modelNameFromTable($state);
                                        $set('Model', 'app\\Models\\' . $modelName);
                                        $set('Resource', self::expectedResourceLabel($modelName));
                                    })
                                    ->required(),

                                Forms\Components\Select::make('Choose Table')
                                    ->options(fn () => self::getAllTableNames())
                                    ->searchable()
                                    ->dehydrated(false)
                                    ->live()
                                    ->helperText('Pick an existing table to load its columns. Uncheck "Create Migration" in that case.')
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $modelName = self::modelNameFromTable($state);
                                        $set('Table Name', $state);
                                        $set('Model', 'app\\Models\\' . $modelName);
                                        $set('Resource', self::expectedResourceLabel($modelName));
                                        $set('Table', self::getTableColumns($state));
                                        $set('create_migration', false);
                                    }),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('Model')
                                    ->default('app\\Models\\')
                                    ->required()
                                    ->helperText('Models are always written to app/Models. Only the class name (last segment) is used.')
                                    ->rule(function () {
                                        return function (string $attribute, $value, \Closure $fail) {
                                            $name = self::getFileName((string) $value);
                                            if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
                                                $fail('The model class name must be PascalCase (e.g. app\\Models\\ProductCategory).');
                                            }
                                        };
                                    })
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $set('Resource', self::expectedResourceLabel(self::getFileName((string) $state)));
                                    }),

                                Forms\Components\TextInput::make('Resource')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Derived from the model name by make:filament-resource.'),
                            ]),
                    ])
                    ->columnSpan(2),

                /********************************************
                 * GENERATION OPTIONS
                 */
                Section::make('Generation Options')
                    ->schema([
                        Forms\Components\Checkbox::make('create_resource')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate Filament resource files (list, create, edit pages)'),

                        Forms\Components\Checkbox::make('create_model')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Create an Eloquent model for the table (skipped if the file already exists)'),

                        Forms\Components\Checkbox::make('simple_resource')
                            ->default(true)
                            ->label('Simple Resource')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate modal-based forms instead of separate pages'),

                        Forms\Components\Checkbox::make('create_migration')
                            ->default(true)
                            ->live()
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Create migration file (only for new tables)'),

                        Forms\Components\Checkbox::make('create_factory')
                            ->default(false)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate factory for test/demo data generation'),

                        Forms\Components\Checkbox::make('create_controller')
                            ->default(false)
                            ->live()
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Create traditional Laravel resource controller'),

                        Forms\Components\Checkbox::make('run_migrate')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Run the migration right away (needed for the resource to read the columns)')
                            ->hidden(fn (Get $get) => ! $get('create_migration')),

                        Forms\Components\Checkbox::make('create_route')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Add a Route::resource() line to routes/web.php')
                            ->hidden(fn (Get $get) => ! $get('create_controller')),

                        Forms\Components\Checkbox::make('create_policy')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate policy + permissions with shield:generate and give them to super_admin (requires Filament Shield and Create Resource)')
                            ->hidden(fn () => ! class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)),

                        Forms\Components\Checkbox::make('create_api')
                            ->label('Create API')
                            ->default(true)
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Generate API endpoints (requires rupadana/filament-api-service)')
                            ->hidden(fn () => ! class_exists(\Rupadana\ApiService\ApiService::class)),
                    ])
                    ->columns(2)
                    ->columnSpan(1),

                /********************************************
                 * TABLE STRUCTURE
                 */
                Section::make('Table Structure')
                    ->schema([
                        Forms\Components\Repeater::make('Table')
                            ->minItems(1)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Field Name')
                                    ->required()
                                    ->regex(self::IDENTIFIER_REGEX)
                                    ->notIn(self::RESERVED_COLUMNS)
                                    ->validationMessages([
                                        'regex' => 'Letters, numbers and underscores only.',
                                        'not_in' => 'id and timestamp columns are added automatically.',
                                    ]),
                                Forms\Components\TextInput::make('translation'),
                                Forms\Components\Select::make('type')
                                    ->native(false)
                                    ->searchable()
                                    ->options(self::columnTypes())
                                    ->default('string')
                                    ->required()
                                    ->live(),
                                Forms\Components\TextInput::make('enum_values')
                                    ->label('Enum values')
                                    ->placeholder('draft,published,archived')
                                    ->helperText('Comma-separated')
                                    ->required(fn (Get $get) => $get('type') === 'enum')
                                    ->visible(fn (Get $get) => $get('type') === 'enum'),
                                Forms\Components\Checkbox::make('nullable')
                                    ->inline(false)
                                    ->default(true),
                                Forms\Components\Select::make('key')
                                    ->options([
                                        '' => 'NULL',
                                        'unique' => 'Unique',
                                        'index' => 'Index',
                                    ])
                                    ->default(''),
                                Forms\Components\TextInput::make('default'),
                                Forms\Components\Textarea::make('comment')
                                    ->autosize(),
                            ])
                            ->columns(8),
                    ])
                    ->columnSpan('full'),

                /********************************************
                 * MIGRATION ADDITIONAL FEATURES
                 */
                Section::make('Migration Additional Features')
                    ->schema([
                        Forms\Components\Checkbox::make('Created_at & Updated_at')
                            ->label('Created_at & Updated_at timestamps')
                            ->default(true)
                            ->inline(),
                        Forms\Components\Checkbox::make('soft_delete')
                            ->label('soft_delete (recycle bin)')
                            ->default(true)
                            ->inline(),
                    ])
                    ->columns(3)
                    ->columnSpan('full'),
            ])
            ->columns(3);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\CreateScaffold::route('/'),
        ];
    }

    /********************************************
     * DATABASE INTROSPECTION (driver-agnostic)
     ********************************************/
    public static function getAllTableNames(): array
    {
        $tables = collect(DbSchema::getTables());

        // Laravel 12+ lists tables from every schema; keep only the current one.
        if (method_exists(DbSchema::getFacadeRoot(), 'getCurrentSchemaName')) {
            $current = DbSchema::getCurrentSchemaName();
            if ($current !== null) {
                $tables = $tables->filter(fn (array $table) => ($table['schema'] ?? $current) === $current);
            }
        }

        $names = $tables->pluck('name')->sort()->values()->all();

        return array_combine($names, $names);
    }

    public static function getTableColumns(string $tableName): array
    {
        if (! preg_match(self::IDENTIFIER_REGEX, $tableName) || ! DbSchema::hasTable($tableName)) {
            return [];
        }

        $keys = [];
        foreach (DbSchema::getIndexes($tableName) as $index) {
            if ($index['primary'] ?? false) {
                continue;
            }
            if (count($index['columns']) !== 1) {
                continue; // composite indexes can't be expressed per column
            }
            $column = $index['columns'][0];
            $keys[$column] = ($index['unique'] ?? false) ? 'unique' : ($keys[$column] ?? 'index');
        }

        $columnDetails = [];

        foreach (DbSchema::getColumns($tableName) as $column) {
            if (in_array(strtolower($column['name']), self::RESERVED_COLUMNS, true) || ($column['auto_increment'] ?? false)) {
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

    /**
     * @return array{0: string, 1: string} [blueprint type, comma-separated enum values]
     */
    public static function mapDatabaseType(string $typeName, string $fullType): array
    {
        $typeName = strtolower($typeName);
        $fullType = strtolower($fullType);

        if ($typeName === 'enum' && preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $fullType, $matches)) {
            return ['enum', implode(',', $matches[1])];
        }

        if ($fullType === 'tinyint(1)') {
            return ['boolean', ''];
        }

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

    protected static function cleanDefault(mixed $default): string
    {
        if ($default === null) {
            return '';
        }

        $default = (string) $default;

        if (strtoupper($default) === 'NULL') {
            return '';
        }

        // Strip surrounding quotes some drivers return, e.g. 'draft'
        if (preg_match("/^'(.*)'(::[a-z ]+)?$/is", $default, $m)) {
            return $m[1];
        }

        return $default;
    }

    /********************************************
     * NAME HELPERS
     ********************************************/
    public static function getFileName(?string $path): string
    {
        $path = str_replace('/', '\\', (string) $path);
        $name = Str::afterLast($path, '\\');

        return Str::beforeLast($name, '.php');
    }

    public static function modelNameFromTable(string $table): string
    {
        return Str::studly(Str::singular($table));
    }

    protected static function expectedResourceLabel(string $modelName): string
    {
        if ($modelName === '') {
            return '';
        }

        return 'app\\Filament\\Resources\\' . Str::plural($modelName) . '\\' . $modelName . 'Resource';
    }

    /********************************************
     * VALIDATION (server side, mirrors the form rules)
     ********************************************/
    public static function validateData(array $data): void
    {
        $table = (string) ($data['Table Name'] ?? '');
        if (! preg_match(self::IDENTIFIER_REGEX, $table)) {
            throw new InvalidArgumentException("Invalid table name [{$table}].");
        }

        $model = self::getFileName($data['Model'] ?? '');
        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $model)) {
            throw new InvalidArgumentException("Invalid model name [{$model}].");
        }

        $types = array_keys(self::columnTypes());

        foreach ($data['Table'] ?? [] as $column) {
            $name = (string) ($column['name'] ?? '');
            if (! preg_match(self::IDENTIFIER_REGEX, $name) || in_array(strtolower($name), self::RESERVED_COLUMNS, true)) {
                throw new InvalidArgumentException("Invalid column name [{$name}].");
            }

            if (! in_array($column['type'] ?? '', $types, true)) {
                throw new InvalidArgumentException("Invalid column type for [{$name}].");
            }

            if (! in_array($column['key'] ?? '', ['', null, 'unique', 'index'], true)) {
                throw new InvalidArgumentException("Invalid key for [{$name}].");
            }

            if (($column['type'] ?? '') === 'enum' && self::parseEnumValues($column['enum_values'] ?? '') === []) {
                throw new InvalidArgumentException("Enum column [{$name}] needs at least one value.");
            }
        }
    }

    public static function parseEnumValues(?string $values): array
    {
        return collect(explode(',', (string) $values))
            ->map(fn ($value) => trim($value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /********************************************
     * MAIN ENTRY
     ********************************************/
    public static function generateFiles(array $data): void
    {
        if (! defined('STDIN')) {
            define('STDIN', fopen('php://stdin', 'r'));
        }

        self::validateData($data);

        $tableName = $data['Table Name'];
        $modelName = self::getFileName($data['Model']);
        $panelId = Filament::getCurrentPanel()?->getId() ?? Filament::getDefaultPanel()->getId();
        $warnings = [];

        /********************************************
         * 1. MIGRATION (written in full, then migrated
         *    BEFORE the resource so --generate sees the columns)
         */
        if ($data['create_migration'] ?? false) {
            if (DbSchema::hasTable($tableName)) {
                $warnings[] = "Table <b>{$tableName}</b> already exists, so no migration was created.";
            } else {
                $migrationPath = database_path('migrations/' . date('Y_m_d_His') . "_create_{$tableName}_table.php");
                File::put($migrationPath, self::buildMigration($data));

                if ($data['run_migrate'] ?? false) {
                    Artisan::call('migrate', ['--no-interaction' => true]);
                }
            }
        }

        /********************************************
         * 2. MODEL (must exist before make:filament-resource)
         */
        if ($data['create_model'] ?? false) {
            $modelPath = app_path("Models/{$modelName}.php");

            if (File::exists($modelPath)) {
                $warnings[] = "Model <b>{$modelName}</b> already exists and was not overwritten.";
            } else {
                File::ensureDirectoryExists(dirname($modelPath));
                File::put($modelPath, self::buildModel($data));
            }
        }

        /********************************************
         * 3. FACTORY
         */
        if ($data['create_factory'] ?? false) {
            Artisan::call('make:factory', [
                'name' => "{$modelName}Factory",
                '--model' => $modelName,
                '--no-interaction' => true,
            ]);
        }

        /********************************************
         * 4. FILAMENT RESOURCE
         * --generate builds the form & table from the DB columns,
         * in the v4/v5 layout (Resource + Schemas/ + Tables/ + Pages/).
         */
        if ($data['create_resource'] ?? false) {
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

        /********************************************
         * 5. CONTROLLER + ROUTE
         */
        if ($data['create_controller'] ?? false) {
            Artisan::call('make:controller', [
                'name' => "{$modelName}Controller",
                '--model' => $modelName,
                '--resource' => true,
                '--no-interaction' => true,
            ]);

            if ($data['create_route'] ?? false) {
                self::addRoutes($tableName, $modelName);
            }
        }

        /********************************************
         * 6. POLICY (Filament Shield)
         */
        /********************************************
         * 6. CLEAR CACHES (in-process), before Shield runs,
         *    so a fresh boot discovers the new resource
         */
        self::clearCaches();

        /********************************************
         * 7. POLICY + PERMISSIONS (Filament Shield)
         * Delegated to `shield:generate` so the policy and permission
         * names follow the app's config/filament-shield.php, and the new
         * permissions are given to super_admin.
         */
        if (($data['create_policy'] ?? false) && ($data['create_resource'] ?? false) && class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)) {
            $warning = self::generateShieldPolicy($modelName, $panelId);

            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        /********************************************
         * 8. API SERVICE (rupadana/filament-api-service)
         */
        if (($data['create_api'] ?? false) && class_exists(\Rupadana\ApiService\ApiService::class)) {
            self::generateApiService($modelName, $panelId);
        }

        /********************************************
         * DONE
         */
        $body = 'The scaffold for <b>' . e($modelName) . '</b> has been created.';
        if ($warnings !== []) {
            $body .= '<br><br>' . implode('<br>', $warnings);
        }

        self::notify(
            Notification::make()
                ->success()
                ->persistent()
                ->title('Scaffold created')
                ->body(new HtmlString($body))
                ->icon('heroicon-o-cube-transparent')
        );
    }

    /********************************************
     * MIGRATION WRITER
     ********************************************/
    public static function buildMigration(array $data): string
    {
        $table = $data['Table Name'];

        $lines = ['$table->id();'];

        foreach ($data['Table'] ?? [] as $column) {
            $lines[] = self::generateColumnDefinition($column) . ';';
        }

        if ($data['Created_at & Updated_at'] ?? false) {
            $lines[] = '$table->timestamps();';
        }

        if ($data['soft_delete'] ?? false) {
            $lines[] = '$table->softDeletes();';
        }

        $body = implode("\n            ", $lines);
        $tableExport = var_export($table, true);

        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create({$tableExport}, function (Blueprint \$table) {
                    {$body}
                });
            }

            public function down(): void
            {
                Schema::dropIfExists({$tableExport});
            }
        };

        PHP;
    }

    public static function generateColumnDefinition(array $column): string
    {
        $type = $column['type'] ?? 'string';
        $name = var_export((string) $column['name'], true);

        if ($type === 'enum') {
            $definition = "\$table->enum({$name}, " . self::exportList(self::parseEnumValues($column['enum_values'] ?? '')) . ')';
        } else {
            $definition = "\$table->{$type}({$name})";
        }

        if ($column['nullable'] ?? false) {
            $definition .= '->nullable()';
        }

        if (filled($column['default'] ?? null)) {
            $definition .= '->default(' . var_export((string) $column['default'], true) . ')';
        }

        if (filled($column['comment'] ?? null)) {
            $definition .= '->comment(' . var_export((string) $column['comment'], true) . ')';
        }

        if (in_array($column['key'] ?? '', ['unique', 'index'], true)) {
            $definition .= "->{$column['key']}()";
        }

        return $definition;
    }

    protected static function exportList(array $values): string
    {
        return '[' . implode(', ', array_map(fn ($value) => var_export((string) $value, true), $values)) . ']';
    }

    /********************************************
     * MODEL WRITER
     ********************************************/
    public static function buildModel(array $data): string
    {
        $modelName = self::getFileName($data['Model']);
        $tableName = $data['Table Name'];
        $softDeletes = (bool) ($data['soft_delete'] ?? false);

        $imports = ['Illuminate\Database\Eloquent\Factories\HasFactory', 'Illuminate\Database\Eloquent\Model'];
        $traits = ['HasFactory'];

        if ($softDeletes) {
            $imports[] = 'Illuminate\Database\Eloquent\SoftDeletes';
            $traits[] = 'SoftDeletes';
        }

        sort($imports);

        $useLines = implode("\n", array_map(fn ($import) => "use {$import};", $imports));
        $traitLine = 'use ' . implode(', ', $traits) . ';';
        $fillable = self::exportList(array_column($data['Table'] ?? [], 'name'));
        $tableExport = var_export($tableName, true);

        return <<<PHP
        <?php

        namespace App\Models;

        {$useLines}

        class {$modelName} extends Model
        {
            {$traitLine}

            protected \$table = {$tableExport};

            protected \$fillable = {$fillable};
        }

        PHP;
    }

    /********************************************
     * ROUTES
     ********************************************/
    public static function addRoutes(string $tableName, string $modelName): void
    {
        $filePath = base_path('routes/web.php');

        if (! File::exists($filePath)) {
            return;
        }

        $uri = str_replace('_', '-', Str::snake($tableName));
        $line = "\nRoute::resource('{$uri}', \\App\\Http\\Controllers\\{$modelName}Controller::class)->only(['index', 'show']);\n";

        $content = File::get($filePath);
        if (! str_contains($content, "{$modelName}Controller::class")) {
            File::append($filePath, $line);
        }
    }

    /********************************************
     * POLICY + PERMISSIONS (Filament Shield)
     ********************************************/

    /**
     * Runs `shield:generate` for the new resource in a separate PHP process.
     * A fresh process is needed because the current request booted the panel
     * before the resource file existed, so Shield can't see it in-process.
     *
     * Existing policies are kept (--ignore-existing-policies); permissions are
     * still created and given to super_admin.
     *
     * @return string|null A warning for the final notification, or null on success.
     */
    public static function generateShieldPolicy(string $modelName, string $panelId): ?string
    {
        $resource = "{$modelName}Resource";

        $command = [
            \Illuminate\Support\php_binary(),
            'artisan',
            'shield:generate',
            "--resource={$resource}",
            "--panel={$panelId}",
            '--option=policies_and_permissions',
            '--ignore-existing-policies',
            '--no-interaction',
        ];

        try {
            $result = Process::path(base_path())->timeout(120)->run($command);
        } catch (Throwable $e) {
            Log::error('[filament-scaffold2] shield:generate could not start', ['exception' => $e]);

            return self::shieldManualHint($resource, $panelId, 'Shield policy generation could not start: ' . e($e->getMessage()));
        }

        Log::info('[filament-scaffold2] shield:generate output', [
            'exit_code' => $result->exitCode(),
            'output' => $result->output(),
            'error' => $result->errorOutput(),
        ]);

        if (! $result->successful()) {
            return self::shieldManualHint($resource, $panelId, 'Shield policy generation failed (see storage/logs).');
        }

        self::notifyPolicyCreated();

        return null;
    }

    protected static function shieldManualHint(string $resource, string $panelId, string $reason): string
    {
        return $reason . '<br>Run it yourself: <code>php artisan shield:generate --resource=' . e($resource)
            . ' --panel=' . e($panelId) . ' --option=policies_and_permissions</code>';
    }

    protected static function notifyPolicyCreated(): void
    {
        $notification = Notification::make()
            ->success()
            ->persistent()
            ->title('Policy & permissions generated')
            ->body('Filament Shield created the policy and permissions for the new resource and gave them to super_admin. Assign them to other roles as needed.')
            ->icon('heroicon-o-shield-check');

        $roleResource = \BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::class;

        if (class_exists($roleResource)) {
            $notification->actions([
                \Filament\Actions\Action::make('view')
                    ->label('Configure Permissions')
                    ->button()
                    ->url($roleResource::getUrl(), shouldOpenInNewTab: true),
                \Filament\Actions\Action::make('close')
                    ->color('gray')
                    ->close(),
            ]);
        }

        self::notify($notification);
    }

    /********************************************
     * API SERVICE
     ********************************************/
    protected static function generateApiService(string $modelName, string $panelId): void
    {
        $resourceClassName = "{$modelName}Resource";

        if (! self::findResourceFile($resourceClassName)) {
            self::notify(
                Notification::make()
                    ->danger()
                    ->title('Resource File Not Found')
                    ->body("Could not find {$resourceClassName}.php under app/Filament/Resources, so no API service was generated.")
            );

            return;
        }

        try {
            Artisan::call('make:filament-api-service', [
                'resource' => $modelName,
                '--panel' => $panelId,
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();

            $succeeded = str_contains($output, 'created') || str_contains($output, 'generated');

            self::notify(
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
            self::notify(
                Notification::make()
                    ->danger()
                    ->title('API Service Generation Failed')
                    ->body('Unexpected error: ' . $e->getMessage())
            );
        }
    }

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

    /********************************************
     * HELPERS
     ********************************************/
    /** optimize:clear also runs filament:optimize-clear (Filament registers it as an optimize hook). */
    protected static function clearCaches(): void
    {
        try {
            Artisan::call('optimize:clear');
        } catch (Throwable $e) {
            Log::warning('[filament-scaffold2] optimize:clear failed', ['message' => $e->getMessage()]);
        }
    }

    /** Shows the notification now and, when possible, stores it in the database for the current user. */
    public static function notify(Notification $notification): void
    {
        $notification->send();

        $user = auth()->user();

        if (! $user || ! method_exists($user, 'notify')) {
            return;
        }

        try {
            $notification->sendToDatabase($user);
        } catch (Throwable $e) {
            // Database notifications are optional (needs the notifications table + Notifiable).
            Log::debug('[filament-scaffold2] Database notification skipped', ['message' => $e->getMessage()]);
        }
    }
}
