<?php

namespace Emmanpbarrameda\FilamentScaffold2\Generators;

use Emmanpbarrameda\FilamentScaffold2\Support\PhpExport;
use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldValidator;

/********************************************
 * ! Writes the "create table" migration file
 * ? The whole file is built here (no patching of the make:migration stub)
 * ? detector: user values go through var_export(), so quotes can't break or inject PHP
 ********************************************/
final class MigrationWriter
{
    /* =========================================
     * ! MARK: Migration file
     * ========================================= */
    public static function buildMigration(array $data): string
    {
        $table = $data['Table Name'];

        // STEP 1: id() first, then one line per column
        $lines = ['$table->id();'];

        foreach ($data['Table'] ?? [] as $column) {
            $lines[] = self::generateColumnDefinition($column) . ';';
        }

        // STEP 2: Optional timestamps + soft deletes
        if ($data['Created_at & Updated_at'] ?? false) {
            $lines[] = '$table->timestamps();';
        }

        if ($data['soft_delete'] ?? false) {
            $lines[] = '$table->softDeletes();';
        }

        // STEP 3: Render the file
        // ? detector: the heredoc indentation IS the output's indentation; don't re-indent this block
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

    /* =========================================
     * ? MARK: Column line
     * ========================================= */

    /** One "$table->type('name')->modifiers()" line (without the trailing semicolon). */
    public static function generateColumnDefinition(array $column): string
    {
        $type = $column['type'] ?? 'string';
        $name = var_export((string) $column['name'], true);

        // STEP 1: Type + name (enum also gets its value list)
        if ($type === 'enum') {
            $definition = "\$table->enum({$name}, " . PhpExport::list(ScaffoldValidator::parseEnumValues($column['enum_values'] ?? '')) . ')';
        } else {
            $definition = "\$table->{$type}({$name})";
        }

        // STEP 2: Modifiers, in this order: nullable → default → comment → key
        // ? detector: empty default/comment are skipped (no ->default('') / ->comment(''))
        if ($column['nullable'] ?? false) {
            $definition .= '->nullable()';
        }

        if (filled($column['default'] ?? null)) {
            $definition .= '->default(' . var_export((string) $column['default'], true) . ')';
        }

        if (filled($column['comment'] ?? null)) {
            $definition .= '->comment(' . var_export((string) $column['comment'], true) . ')';
        }

        // ? detector: only unique/index are allowed; anything else (incl. '') adds nothing, never "->()"
        if (in_array($column['key'] ?? '', ['unique', 'index'], true)) {
            $definition .= "->{$column['key']}()";
        }

        return $definition;
    }
}
