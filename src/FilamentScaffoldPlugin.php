<?php

namespace Emmanpbarrameda\FilamentScaffold2;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Emmanpbarrameda\FilamentScaffold2\Resources\ScaffoldResource;

class FilamentScaffoldPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-scaffold2';
    }

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
