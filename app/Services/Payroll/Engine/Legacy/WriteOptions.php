<?php

namespace App\Services\Payroll\Engine\Legacy;

/**
 * Run metadata recorded with the payroll.
 */
final class WriteOptions
{
    public function __construct(
        public string $engineVersion = '',
        public string $inputHash = '',
        public int $actorID = 0,
        public bool $allowReopen = false,
        public string $reopenReason = '',
    ) {}
}
