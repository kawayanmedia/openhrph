<?php

namespace KawayanMedia\Attendance\Import;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use KawayanMedia\Attendance\Contracts\AttendanceImport;

class FileImport implements AttendanceImport
{
    public $filePath;

    public $timesheets;

    public $importFromDate;

    public $importToDate;

    public $importFrom = 'file'; // can be file or from machine

    public function __construct()
    {
        $this->importFromDate = '2022-12-31';
    }

    public function setLimit($importFromDate = null, $importToDate = null)
    {
        $this->importFromDate = $importFromDate;
        $this->importToDate = $importToDate;
    }

    public function upload($request)
    {
        // Upload and get the path
        $this->filePath = $request->file->store('attendance/logs');
    }

    public function handle()
    {

    }

    public function process()
    {
        // LazyCollection::make(function () {
        //     $handle = fopen(storage_path('app/'.$this->filePath), 'r');

        //     while (($line = fgets($handle)) !== false) {
        //         yield $line;
        //     }
        // })
        //     ->chunk(4)
        //     ->map(function ($lines) {
        //         return $lines;
        //     })
        //     ->each(function ($entry) {
        //         // Process the log entry...
        //     });

        set_time_limit(0);

        //$this->filePath = storage_path('app/attendance/logs/hospital.dat');
        $filePath = storage_path('app/'.$this->filePath);
        // Read each line of .txt or .dat file
        if ($fd = fopen($filePath, 'r')) {
            while (! feof($fd)) {
                $lines[] = fgets($fd, 4096);
            }

            fclose($fd);
        }

        sort($lines);

        $employeeId = '';

        $this->timesheets = [];

        foreach ($lines as $line) {
            $line = ltrim($line);

            if (count(explode("\t", $line)) != 6) {
                $employeeId = '';
                $dateTime = '';
                $stat = '';
                $logType = '';
            } else {
                // Biometric machine use tabs to separate each logs
                // Example:  2458	2013-03-13 16:58:11	1	1	1	0
                [$employeeId, $dateTime, $stat, $logType] = explode("\t", $line);
            }

            $employeeId = sprintf('%03d', $employeeId);

            if (count(explode(' ', $dateTime)) != 2) {
                $date = '';
                $time = '';
            } else {
                [$date, $time] = explode(' ', $dateTime);
            }

            // Dont process some old data or employee id is blank
            if (($date < $this->importFromDate) or $employeeId == '') {
                continue;
            }

            // $logType 0 = in , 1 = out
            $this->timesheets[$employeeId][$date][] = [
                'employee_id' => $employeeId,
                'log_date' => $date,
                'time' => $time,
                'log_type' => (int) $logType,
                // 'date_time' => $dateTime,
            ];
        }

        // $this->deleteFile();

        return $this->timesheets;
    }

    public function deleteFile()
    {
        Storage::delete($this->filePath);
    }

    public function getTimesheets()
    {
        return $this->timesheets;
    }
}
