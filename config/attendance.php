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

];
