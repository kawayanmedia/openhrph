<?php

namespace KawayanMedia\Attendance\Contracts;

interface AttendanceImport
{
    public function handle();

    public function getTimesheets();
}
