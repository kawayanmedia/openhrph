<?php

namespace App\Livewire\Admin\Fields;

class Badge extends Field
{
    public string $component = 'badge';

    public array $map = [];

    public function __construct()
    {
        // Badge is a display-only field, matching Nova's Badge field.
        $this->showOnCreating = false;
        $this->showOnUpdating = false;
    }

    public function map(array $map): static
    {
        $this->map = $map;

        return $this;
    }

    /**
     * Resolve a Nova colour name to Tailwind classes.
     */
    public function colorClasses(string $color): string
    {
        return match ($color) {
            'success' => 'bg-emerald-100 text-emerald-800',
            'danger' => 'bg-red-100 text-red-800',
            'warning' => 'bg-amber-100 text-amber-800',
            'info' => 'bg-sky-100 text-sky-800',
            default => 'bg-slate-100 text-slate-800',
        };
    }
}
