<?php

namespace App\Services\Payroll\Computation;

/**
 * A company holiday within the payroll period.
 */
final class Holiday
{
    public function __construct(
        public string $date = '',
        public string $code = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: (string) ($data['date'] ?? ''),
            code: (string) ($data['code'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['date' => $this->date, 'code' => $this->code];
    }
}
