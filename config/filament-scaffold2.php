<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable the Scaffold Manager
    |--------------------------------------------------------------------------
    |
    | The Scaffold Manager writes PHP files (models, migrations, resources,
    | policies, routes) to disk from user input. Keep it OFF everywhere except
    | your local machine. Turn it on with FILAMENT_SCAFFOLD2_ENABLED=true in
    | your local .env only.
    |
    */

    'enabled' => env('FILAMENT_SCAFFOLD2_ENABLED', false),

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
