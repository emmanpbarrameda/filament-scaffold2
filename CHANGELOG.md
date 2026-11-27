# Changelog

All notable changes to `emmanpbarrameda/filament-scaffold2` will be documented in this file.

## 1.0.2 - 2026-11-28

### Fixed
* Shield permissions had no effect on the Scaffold Manager page: the package's `Scaffold` model had no policy, and Laravel's auto-discovery never looks in `App\Policies` for a package model, so Filament allowed every user. The package now registers `App\Policies\ScaffoldPolicy` (configurable via `filament-scaffold2.policy`) when the class exists and the app hasn't registered its own.

### Docs
* README: generate the policy with `shield:generate --resource=ScaffoldResource`.

## 1.0.1 - 2026-11-27

### Fixed
* Fatal error on Filament v4 versions where `Resource::getNavigationLabel()` returns `string` ("Declaration of ScaffoldResource::getNavigationLabel(): Htmlable|string must be compatible"). The override now returns `string`, which is compatible with every v4 and v5 release.

### Changed
* `enabled` now defaults to ON when `APP_ENV=local` and OFF in every other environment. `FILAMENT_SCAFFOLD2_ENABLED=true|false` still overrides it anywhere. If you published the config before 1.0.1, update its `enabled` line to get the new default.
* README: install as a regular dependency with `FilamentScaffoldPlugin::make()`.

## 1.0.0 - 2026-11-25

First release as `emmanpbarrameda/filament-scaffold2`, rebuilt from solution-forest/filament-scaffold for Filament v4/v5.

### Renamed
* Package `solution-forest/filament-scaffold` → `emmanpbarrameda/filament-scaffold2`.
* Namespace `Solutionforest\FilamentScaffold` → `Emmanpbarrameda\FilamentScaffold2`.
* Config file, config key, plugin ID and publish tag → `filament-scaffold2` / `filament-scaffold2-config`.
* Environment variable → `FILAMENT_SCAFFOLD2_ENABLED`.

### Changed
* Requires Filament v4 or v5 (uses the v4+ Schemas API), PHP 8.2+, Laravel 11+.
* Disabled by default: set `FILAMENT_SCAFFOLD2_ENABLED=true` in your local `.env` only.
* Navigation group, icon, sort and label come from `config/filament-scaffold2.php`.
* Migrations are written in full by the package instead of regex-patching the `make:migration` stub.
* Generation order: migration (+ migrate) and model are created before `make:filament-resource --generate`, so the generated form and table read the real columns.
* Resources use Filament's own v4/v5 layout (Resource + Schemas/ + Tables/ + Pages/); the old regex overwrite of `form()`/`table()` was removed.
* Table/column introspection uses Laravel's `Schema` builder (works on MySQL, MariaDB, SQLite, PostgreSQL) instead of `SHOW TABLES` / `SHOW COLUMNS`.
* Cache clearing runs `optimize:clear` and `filament:clear-cached-components` in-process (no `proc_open`, no `config:cache`/`route:cache`).
* The scaffold page no longer runs inside the panel's database transaction.
* Database notifications go to the current user and are skipped when the app has no notifications table.

### Internal
* `ScaffoldResource` (~1000 lines) split into focused classes, with no behavior change: `Schemas\ScaffoldForm`, `Actions\GenerateScaffold`, `Generators\{MigrationWriter, ModelWriter, RouteWriter}`, `Database\TableInspector`, `Integrations\{ShieldPolicyGenerator, ApiServiceGenerator}`, `Support\{ScaffoldRules, ScaffoldValidator, Naming, PhpExport, ScaffoldNotifier}`.
* The static helpers that used to live on `ScaffoldResource` (`generateFiles()`, `buildMigration()`, `getTableColumns()`, `validateData()`, ...) now live only on those classes; `ScaffoldResource` keeps navigation, access, form and pages.

### Added
* Enum columns: comma-separated values field; enum values are read back from existing MySQL tables.
* Server-side validation of table, model and column names, column types and index keys before any file is written.

### Fixed
* Generated policies used Shield 3 permission names (`view_any::model`), so Shield 4 denied access and new resources were hidden. Policies and permissions are now created by `shield:generate`, follow `config/filament-shield.php`, and are given to `super_admin`. Existing policies are kept.
* `key = NULL` produced `->()` (invalid PHP) and empty default/comment produced `->default('')` / `->comment('')`.
* Values in default/comment are escaped with `var_export()`, so quotes can't break or inject into generated PHP.
* Model class name extraction on Linux/macOS (`basename()` doesn't split backslashes there).
* Factory and controller were named after the table (`productsFactory`) instead of the model (`ProductFactory`).
* `routes\web.php` path only worked on Windows; routes now use the controller FQCN, kebab-case URIs and are not duplicated.
* "Create Route" without "Create Controller" produced a broken route; the option is now only shown with the controller.
* `HasPanelShield` (a User-model trait) is no longer added to every generated model.
* API service lookup finds resources in the nested v4/v5 folders.
* New resources didn't appear in the sidebar or the Shield Roles page when the panel components were cached (`php artisan optimize` / `filament:cache-components`). Filament only hooks `filament:optimize-clear` into `optimize:clear` in the console, so the scaffold page (a web request) now clears the panel component cache explicitly (`filament:clear-cached-components`).
* Existing models/policies are not overwritten; existing tables skip the migration with a warning.
* Removed dependencies on application classes (`App\Traits\HasNavigationConfig`, `App\Filament\Resources\VariableStorerResource`).
* Removed `composer.json` aliases to classes that don't exist.

---

# Original history (solution-forest/filament-scaffold)

## 0.0.8 - 2025-01-14

### What's Changed

* Model form should singular by @hazbu in https://github.com/solutionforest/filament-scaffold/pull/5

### New Contributors

* @hazbu made their first contribution in https://github.com/solutionforest/filament-scaffold/pull/5

**Full Changelog**: https://github.com/solutionforest/filament-scaffold/compare/0.0.7...0.0.8

## 0.0.7 - 2024-08-13

### What's Changed

* Update README.md by @pranab-acharya in https://github.com/solutionforest/filament-scaffold/pull/3
* Fix undefined array key by @joy2fun in https://github.com/solutionforest/filament-scaffold/pull/4
* Fix windows or linux basename path issue

### New Contributors

* @pranab-acharya made their first contribution in https://github.com/solutionforest/filament-scaffold/pull/3
* @joy2fun made their first contribution in https://github.com/solutionforest/filament-scaffold/pull/4

**Full Changelog**: https://github.com/solutionforest/filament-scaffold/compare/0.0.6...0.0.7

## 0.0.6 - 2024-08-02

### What's Changed

* Improvements by emmanpbarrameda - 080224 by @emmanpbarrameda in https://github.com/solutionforest/filament-scaffold/pull/2

### New Contributors

* @emmanpbarrameda made their first contribution in https://github.com/solutionforest/filament-scaffold/pull/2

**Full Changelog**: https://github.com/solutionforest/filament-scaffold/compare/0.0.5...0.0.6

## 0.0.5 - 2024-07-26

**Full Changelog**: https://github.com/solutionforest/filament-scaffold/compare/0.0.4...0.0.5

## 0.0.4 - 2024-07-25

**Full Changelog**: https://github.com/solutionforest/filament-scaffold/compare/0.0.3...0.0.4

## 0.0.3 - 2024-07-25

**Full Changelog**: https://github.com/solutionforest/filament-scaffold/compare/0.0.2...0.0.3

## 0.0.1 - 2024-07-24

### What's Changed

* Bump dependabot/fetch-metadata from 1.6.0 to 2.2.0 by @dependabot in https://github.com/solutionforest/filament-scaffold/pull/1

### New Contributors

* @dependabot made their first contribution in https://github.com/solutionforest/filament-scaffold/pull/1

**Full Changelog**: https://github.com/solutionforest/filament-scaffold/commits/0.0.1

## 1.0.0 - 202X-XX-XX

- initial release
