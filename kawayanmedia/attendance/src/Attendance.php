<?php

namespace KawayanMedia\Attendance;

use KawayanMedia\Attendance\Devices\DriverManager;

class Attendance
{
    /**
     * The registry that resolves a device protocol to its driver.
     */
    public static function devices(): DriverManager
    {
        return app(DriverManager::class);
    }
}
