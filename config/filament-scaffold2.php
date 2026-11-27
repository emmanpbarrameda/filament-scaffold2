<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable the Scaffold Manager
    |--------------------------------------------------------------------------
    |
    | The Scaffold Manager writes PHP files (models, migrations, resources,
    | policies, routes) to disk from user input.
    |
    | Default: ON when APP_ENV=local, OFF in every other environment.
    | Set FILAMENT_SCAFFOLD2_ENABLED=true or false in .env to override it.
    | Enabling it on a production server is possible but not recommended.
    |
    */

    'enabled' => env('FILAMENT_SCAFFOLD2_ENABLED', env('APP_ENV', 'production') === 'local'),

    /*
    |--------------------------------------------------------------------------
    | Policy for the Scaffold Manager page
    |--------------------------------------------------------------------------
    |
    | Registered for the package's Scaffold model when the class exists, so
    | role permissions (e.g. Filament Shield) control who can open the page.
    | Generate it with: php artisan shield:generate --resource=ScaffoldResource
    | Set to null to skip it.
    |
    */

    'policy' => 'App\\Policies\\ScaffoldPolicy',

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'group' => 'System',
        'icon' => 'heroicon-o-cube-transparent',
        'sort' => 99,
        'label' => 'Scaffold2 Manager',
    ],

];
