<?php

namespace App\Imports\Workbook;

/**
 * A single reason the workbook cannot be imported, located as precisely as the
 * workbook allows.
 */
final readonly class ImportProblem
{
    public function __construct(
        public ?string $sheet,
        public ?int $row,
        public ?string $column,
        public ?string $heading,
        public string $message,
    ) {}

    public static function workbook(string $message): self
    {
        return new self(null, null, null, null, $message);
    }

    public static function sheet(string $sheet, string $message): self
    {
        return new self($sheet, null, null, null, $message);
    }

    public static function cell(
        string $sheet,
        int $row,
        ?string $column,
        ?string $heading,
        string $message,
    ): self {
        return new self($sheet, $row, $column, $heading, $message);
    }

    /**
     * A human readable "where" for the problem.
     */
    public function location(): string
    {
        $parts = [];

        if ($this->sheet !== null) {
            $parts[] = $this->sheet;
        }

        if ($this->row !== null) {
            $parts[] = 'Row '.$this->row;
        }

        if ($this->column !== null) {
            $parts[] = 'Column '.$this->column.($this->heading !== null ? ' ("'.$this->heading.'")' : '');
        } elseif ($this->heading !== null) {
            $parts[] = 'Column "'.$this->heading.'"';
        }

        return $parts === [] ? 'Workbook' : implode(' · ', $parts);
    }

    /**
     * @return array{sheet: string|null, row: int|null, column: string|null, heading: string|null, message: string, location: string}
     */
    public function toArray(): array
    {
        return [
            'sheet' => $this->sheet,
            'row' => $this->row,
            'column' => $this->column,
            'heading' => $this->heading,
            'message' => $this->message,
            'location' => $this->location(),
        ];
    }
}
