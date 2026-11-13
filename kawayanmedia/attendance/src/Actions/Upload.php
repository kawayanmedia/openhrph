<?php

namespace KawayanMedia\Attendance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use KawayanMedia\Attendance\Import\FileImport;

class Upload extends Controller
{
    public function upload(Request $request)
    {
        $log = new FileImport;
        $log->upload($request);
        $log->process();

        return $log->getTimesheets();
    }
}
