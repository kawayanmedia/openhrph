<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Punch debounce
    |--------------------------------------------------------------------------
    |
    | A punch recorded within this many seconds of the employee's previous
    | punch is treated as an accidental double tap or a device retry instead
    | of a real time-out.
    |
    */

    'punch_debounce_seconds' => (int) env('ATTENDANCE_PUNCH_DEBOUNCE_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Night differential window
    |--------------------------------------------------------------------------
    |
    | Fallback when the company has not configured its own window. The
    | Philippine private-sector default is 22:00-06:00; the government sector
    | sets 18:00-06:00 through its jurisdiction defaults. A shift may override
    | both values.
    |
    */

    'night_window_start' => env('ATTENDANCE_NIGHT_WINDOW_START', '22:00'),

    'night_window_end' => env('ATTENDANCE_NIGHT_WINDOW_END', '06:00'),

    /*
    |--------------------------------------------------------------------------
    | Grace period
    |--------------------------------------------------------------------------
    |
    | Minutes after the shift start before an arrival is counted as late when
    | the shift itself does not define its own grace period.
    |
    */

    'grace_minutes' => (int) env('ATTENDANCE_GRACE_MINUTES', 0),

];
