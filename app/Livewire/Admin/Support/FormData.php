<?php

namespace App\Livewire\Admin\Support;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Read-only view of the current form values passed to dependsOn callbacks.
 *
 * Mirrors Nova's FormData API so resource callbacks can be ported verbatim.
 *
 * @implements ArrayAccess<string, mixed>
 */
class FormData implements Arrayable, ArrayAccess
{
    public function __construct(protected array $data = []) {}

    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Set a value only when the current value is empty.
     */
    public function fill(string $key, mixed $value): void
    {
        if (! isset($this->data[$key]) || $this->data[$key] === '') {
            $this->data[$key] = $value;
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
