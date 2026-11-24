<?php

namespace Emmanpbarrameda\FilamentScaffold2\Integrations;

use Emmanpbarrameda\FilamentScaffold2\Support\ScaffoldNotifier;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/********************************************
 * ! Policy + permissions via Filament Shield (optional package)
 * ? Delegated to `shield:generate`, so policy and permission names follow the
 *   app's config/filament-shield.php (Shield 4: "ViewAny:Model") and super_admin gets them
 ********************************************/
final class ShieldPolicyGenerator
{
    public static function isAvailable(): bool
    {
        return class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class);
    }

    /* =========================================
     * ! MARK: shield:generate
     * ========================================= */

    /**
     * Runs `shield:generate` for the new resource in a separate PHP process.
     *
     * @return string|null A warning for the final notification, or null on success.
     */
    public static function generateShieldPolicy(string $modelName, string $panelId): ?string
    {
        $resource = "{$modelName}Resource";

        // STEP 1: Build the command
        // ? detector: a FRESH process is required — this request booted the panel before the
        //   resource file existed, so Shield can't see the new resource in-process
        // ? detector: --ignore-existing-policies keeps hand-edited policies; permissions are still created
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

        // STEP 2: Run it (a process that can't start becomes a warning, not a failure)
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

        // STEP 3: Non-zero exit → warning with the command to run by hand
        if (! $result->successful()) {
            return self::shieldManualHint($resource, $panelId, 'Shield policy generation failed (see storage/logs).');
        }

        // STEP 4: Success notification (with a link to the Roles page)
        self::notifyPolicyCreated();

        return null;
    }

    /* =========================================
     * ? MARK: Messages
     * ========================================= */
    public static function shieldManualHint(string $resource, string $panelId, string $reason): string
    {
        return $reason . '<br>Run it yourself: <code>php artisan shield:generate --resource=' . e($resource)
            . ' --panel=' . e($panelId) . ' --option=policies_and_permissions</code>';
    }

    public static function notifyPolicyCreated(): void
    {
        $notification = Notification::make()
            ->success()
            ->persistent()
            ->title('Policy & permissions generated')
            ->body('Filament Shield created the policy and permissions for the new resource and gave them to super_admin. Assign them to other roles as needed.')
            ->icon('heroicon-o-shield-check');

        // ? detector: class_exists guard — the button is only added when Shield's RoleResource is at this path
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

        ScaffoldNotifier::notify($notification);
    }
}
