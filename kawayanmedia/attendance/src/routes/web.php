<?php

use Illuminate\Support\Facades\Route;

Route::post('test/attendance/upload', 'Islestech\Attendance\Controllers\Upload@upload');
