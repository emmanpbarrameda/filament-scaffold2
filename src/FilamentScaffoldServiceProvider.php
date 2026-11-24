<?php

namespace Emmanpbarrameda\FilamentScaffold2;

use Emmanpbarrameda\FilamentScaffold2\Testing\TestsFilamentScaffold;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/********************************************
 * ? Package service provider (auto-discovered via composer.json extra.laravel)
 * Publishes config/filament-scaffold2.php (tag: filament-scaffold2-config)
 ********************************************/
class FilamentScaffoldServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-scaffold2';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        Testable::mixin(new TestsFilamentScaffold);
    }
}
