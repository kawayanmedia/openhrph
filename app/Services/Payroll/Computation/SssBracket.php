<?php

namespace App\Services\Payroll\Computation;

/**
 * One row of the SSS contribution schedule for a monthly salary credit band.
 * Amounts are monthly centavos.
 */
final class SssBracket
{
    public function __construct(
        public int $mscFrom = 0,
        public int $mscTo = 0,
        public int $msc = 0,
        public int $ee = 0,
        public int $er = 0,
        public int $ec = 0,
        public int $wispEE = 0,
        public int $wispER = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            mscFrom: (int) ($data['msc_from'] ?? 0),
            mscTo: (int) ($data['msc_to'] ?? 0),
            msc: (int) ($data['msc'] ?? 0),
            ee: (int) ($data['ee'] ?? 0),
            er: (int) ($data['er'] ?? 0),
            ec: (int) ($data['ec'] ?? 0),
            wispEE: (int) ($data['wisp_ee'] ?? 0),
            wispER: (int) ($data['wisp_er'] ?? 0),
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'msc_from' => $this->mscFrom,
            'msc_to' => $this->mscTo,
            'msc' => $this->msc,
            'ee' => $this->ee,
            'er' => $this->er,
            'ec' => $this->ec,
            'wisp_ee' => $this->wispEE,
            'wisp_er' => $this->wispER,
        ];
    }
}
