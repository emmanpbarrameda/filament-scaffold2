<?php

namespace Emmanpbarrameda\FilamentScaffold2\Generators;

use Emmanpbarrameda\FilamentScaffold2\Support\Naming;
use Emmanpbarrameda\FilamentScaffold2\Support\PhpExport;

/********************************************
 * ! Writes the Eloquent model file (always app/Models)
 * ? Only the last segment of the "Model" input is used as the class name
 ********************************************/
final class ModelWriter
{
    /* =========================================
     * ! MARK: Model file
     * ========================================= */
    public static function buildModel(array $data): string
    {
        $modelName = Naming::getFileName($data['Model']);
        $tableName = $data['Table Name'];
        $softDeletes = (bool) ($data['soft_delete'] ?? false);

        // STEP 1: Imports + traits (SoftDeletes only when the migration has softDeletes())
        $imports = ['Illuminate\Database\Eloquent\Factories\HasFactory', 'Illuminate\Database\Eloquent\Model'];
        $traits = ['HasFactory'];

        if ($softDeletes) {
            $imports[] = 'Illuminate\Database\Eloquent\SoftDeletes';
            $traits[] = 'SoftDeletes';
        }

        sort($imports);

        // STEP 2: $table + $fillable (every column from the repeater)
        $useLines = implode("\n", array_map(fn ($import) => "use {$import};", $imports));
        $traitLine = 'use ' . implode(', ', $traits) . ';';
        $fillable = PhpExport::list(array_column($data['Table'] ?? [], 'name'));
        $tableExport = var_export($tableName, true);

        // STEP 3: Render the file
        // ? detector: the heredoc indentation IS the output's indentation; don't re-indent this block
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
}
