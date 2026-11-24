<?php

namespace Emmanpbarrameda\FilamentScaffold2;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource;

/********************************************
 * ! Panel plugin: ->plugins([FilamentScaffoldPlugin::make()])
 * ? Registers ScaffoldResource only when filament-scaffold2.enabled is true
 ********************************************/
class FilamentScaffoldPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-scaffold2';
    }

    /* =========================================
     * ! MARK: Register
     * ? detector: first gate — when disabled the resource isn't registered at all
     *   (ScaffoldResource::canAccess() is the second gate)
     * ========================================= */
    public function register(Panel $panel): void
    {
        if (config('filament-scaffold2.enabled', false)) {
            $panel->resources([
                ScaffoldResource::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /* =========================================
     * ? MARK: Factory helpers
     * ========================================= */
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
