<?php

namespace App\Imports\Workbook;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * One column of an import sheet.
 *
 * The same object builds the Excel template header, normalises a cell value and
 * supplies the validation rules, so the sample workbook and the importer can
 * never drift apart.
 */
final class Column
{
    /**
     * @param  array<int, mixed>  $rules  Laravel validation rules for the normalised value.
     * @param  array<string, string>  $messages  Rule name => message override.
     * @param  array<int, string>  $aliases  Alternative headings accepted in the workbook.
     * @param  array<int, string>  $options  Allowed values, used for the template dropdown.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $heading,
        public readonly ColumnType $type = ColumnType::Text,
        public readonly bool $required = false,
        public readonly array $rules = [],
        public readonly array $messages = [],
        public readonly array $aliases = [],
        public readonly array $options = [],
        public readonly string $example = '',
        public readonly string $help = '',
    ) {}

    /**
     * Whether a heading from the uploaded workbook belongs to this column.
     */
    public function matches(string $heading): bool
    {
        $signature = self::signature($heading);

        if ($signature === self::signature($this->heading)) {
            return true;
        }

        foreach ($this->aliases as $alias) {
            if ($signature === self::signature($alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalise a raw cell value.
     *
     * @return array{0: mixed, 1: string|null} The normalised value and an error
     *                                         message, if the cell cannot be read.
     */
    public function normalise(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
        }

        if ($raw === null || $raw === '') {
            return [null, null];
        }

        return match ($this->type) {
            ColumnType::Text => [is_scalar($raw) ? (string) $raw : null, null],
            ColumnType::Integer => $this->normaliseInteger($raw),
            ColumnType::Decimal => $this->normaliseDecimal($raw),
            ColumnType::Date => $this->normaliseDate($raw),
            ColumnType::Time => $this->normaliseTime($raw),
            ColumnType::Boolean => $this->normaliseBoolean($raw),
        };
    }

    /**
     * Headings ignore case, spacing, punctuation and an optional "*" or
     * "(required)" marker.
     */
    public static function signature(string $heading): string
    {
        return (string) Str::of($heading)
            ->lower()
            ->replaceMatches('/\(required\)/i', '')
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private function normaliseInteger(mixed $raw): array
    {
        $value = $this->stripNumberDecorations($raw);

        if ($value === '' || ! is_numeric($value) || (float) $value !== floor((float) $value)) {
            return [null, 'must be a whole number.'];
        }

        return [(int) $value, null];
    }

    /**
     * @return array{0: float|null, 1: string|null}
     */
    private function normaliseDecimal(mixed $raw): array
    {
        $value = $this->stripNumberDecorations($raw);

        if ($value === '' || ! is_numeric($value)) {
            return [null, 'must be a number.'];
        }

        return [(float) $value, null];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function normaliseDate(mixed $raw): array
    {
        if (is_int($raw) || is_float($raw)) {
            try {
                return [ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d'), null];
            } catch (Throwable) {
                return [null, 'must be a date written as YYYY-MM-DD.'];
            }
        }

        $value = trim((string) $raw);

        foreach (['Y-m-d', 'Y-m-d H:i:s', 'Y/m/d', 'm/d/Y', 'd/m/Y'] as $format) {
            $date = $this->parseWithFormat($value, $format);

            if ($date !== null) {
                return [$date->format('Y-m-d'), null];
            }
        }

        return [null, 'must be a date written as YYYY-MM-DD.'];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function normaliseTime(mixed $raw): array
    {
        if (is_int($raw) || is_float($raw)) {
            try {
                return [ExcelDate::excelToDateTimeObject((float) $raw)->format('H:i'), null];
            } catch (Throwable) {
                return [null, 'must be a time written as HH:MM (24-hour).'];
            }
        }

        $value = trim((string) $raw);

        foreach (['H:i', 'H:i:s', 'g:i A', 'g:iA'] as $format) {
            $time = $this->parseWithFormat($value, $format);

            if ($time !== null) {
                return [$time->format('H:i'), null];
            }
        }

        return [null, 'must be a time written as HH:MM (24-hour).'];
    }

    /**
     * @return array{0: bool|null, 1: string|null}
     */
    private function normaliseBoolean(mixed $raw): array
    {
        if (is_bool($raw)) {
            return [$raw, null];
        }

        $value = (string) $raw |> trim(...) |> strtolower(...);

        return match ($value) {
            'yes', 'y', 'true', '1' => [true, null],
            'no', 'n', 'false', '0' => [false, null],
            default => [null, 'must be Yes or No.'],
        };
    }

    private function stripNumberDecorations(mixed $raw): string
    {
        $value = trim((string) $raw);

        $value = (string) preg_replace('/^(?:php|₱|\$|€|£)\s*/i', '', $value);

        return str_replace([',', ' ', "\u{00A0}"], '', $value);
    }

    private function parseWithFormat(string $value, string $format): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat($format, $value);
        } catch (Throwable) {
            return null;
        }

        if (! $date instanceof CarbonImmutable) {
            return null;
        }

        $errors = CarbonImmutable::getLastErrors();

        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $date;
    }
}
