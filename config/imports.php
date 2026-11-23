<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    |
    | Data imports are validated in memory before anything is written, so the
    | upload and the number of rows per sheet are capped.
    |
    */

    'max_upload_kb' => (int) env('IMPORT_MAX_UPLOAD_KB', 10240),

    'max_rows' => (int) env('IMPORT_MAX_ROWS', 5000),

    /*
    |--------------------------------------------------------------------------
    | Problem list
    |--------------------------------------------------------------------------
    |
    | The import page lists every problem, but a very broken workbook can have
    | thousands. Only the first N are rendered; the rest can be downloaded.
    |
    */

    'display_problems' => (int) env('IMPORT_DISPLAY_PROBLEMS', 200),

];
