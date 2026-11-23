<?php

namespace App\Imports\Workbook;

use Illuminate\Support\Str;

/**
 * Names that rows in the workbook may point at: records that already exist in
 * the company plus records that will be created from this same workbook.
 */
final class ReferenceIndex
{
    /**
     * @var array<string, array{names: array<string, int>, codes: array<string, int>}>
     */
    private array $ids = [];

    /**
     * @var array<string, array{names: array<string, string>, codes: array<string, string>}>
     */
    private array $pending = [];

    public static function normalise(string $value): string
    {
        return Str::lower(Str::squish($value));
    }

    public function seed(string $type, string $name, ?string $code, int $id): void
    {
        $this->ids[$type]['names'][self::normalise($name)] = $id;

        if ($code !== null && trim($code) !== '') {
            $this->ids[$type]['codes'][self::normalise($code)] = $id;
        }
    }

    public function markPending(string $type, string $name, ?string $code, string $location): void
    {
        $this->pending[$type]['names'][self::normalise($name)] = $location;

        if ($code !== null && trim($code) !== '') {
            $this->pending[$type]['codes'][self::normalise($code)] = $location;
        }
    }

    public function registerCreated(string $type, string $name, ?string $code, int $id): void
    {
        $this->ids[$type]['names'][self::normalise($name)] = $id;

        if ($code !== null && trim($code) !== '') {
            $this->ids[$type]['codes'][self::normalise($code)] = $id;
        }
    }

    public function id(string $type, string $value): ?int
    {
        $key = self::normalise($value);

        return $this->ids[$type]['names'][$key]
            ?? $this->ids[$type]['codes'][$key]
            ?? null;
    }

    public function exists(string $type, string $value): bool
    {
        $key = self::normalise($value);

        return isset($this->ids[$type]['names'][$key])
            || isset($this->ids[$type]['codes'][$key])
            || isset($this->pending[$type]['names'][$key])
            || isset($this->pending[$type]['codes'][$key]);
    }

    public function pendingLocation(string $type, string $value): ?string
    {
        $key = self::normalise($value);

        return $this->pending[$type]['names'][$key]
            ?? $this->pending[$type]['codes'][$key]
            ?? null;
    }

    /**
     * Whether a reference already exists or will be created by this workbook.
     *
     * @return array{status: string, location: string|null}|null
     */
    public function details(string $type, string $value): ?array
    {
        if ($this->id($type, $value) !== null) {
            return ['status' => 'existing', 'location' => null];
        }

        $location = $this->pendingLocation($type, $value);

        if ($location !== null) {
            return ['status' => 'new', 'location' => $location];
        }

        return null;
    }

    public function status(string $type, string $value): ?string
    {
        return $this->details($type, $value)['status'] ?? null;
    }
}
