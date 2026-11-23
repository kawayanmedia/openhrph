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
    | Preview
    |--------------------------------------------------------------------------
    |
    | How many rows of each sheet are shown in the preview table. Counts always
    | cover every row in the workbook.
    |
    */

    'preview_rows' => (int) env('IMPORT_PREVIEW_ROWS', 5),

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
