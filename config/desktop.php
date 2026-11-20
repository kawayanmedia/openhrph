<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Desktop Debug Mode
    |--------------------------------------------------------------------------
    |
    | Packaged desktop builds ship with APP_DEBUG disabled, because NativePHP
    | treats debug mode as a development run and would otherwise keep the
    | SQLite database, uploads and logs inside the installed application
    | folder instead of the per-user app-data folder. Enabling this shows
    | Laravel's error pages, stack traces and the Electron DevTools while the
    | application keeps using the per-user paths.
    |
    */

    'debug' => (bool) env('DESKTOP_DEBUG', false),

];
