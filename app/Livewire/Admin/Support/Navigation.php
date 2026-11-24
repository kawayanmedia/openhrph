<?php

namespace App\Livewire\Admin\Support;

use App\Sectors\SectorManager;
use App\Support\Installation\Installation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Navigation
{
    /**
     * The filtered menu sections ready for rendering.
     */
    public static function sections(): Collection
    {
        return collect(config('admin.menu', []))
            ->merge(Installation::jurisdiction()->adminMenu())
            ->map(fn (array $section) => static::normalizeSection($section))
            ->filter()
            ->values();
    }

    protected static function normalizeSection(array $section): ?array
    {
        if (! static::canSee($section['can'] ?? null, $section['sector'] ?? $section['sectors'] ?? null, $section['jurisdiction'] ?? null)) {
            return null;
        }

        $path = trim(config('admin.path'), '/');

        if (($section['type'] ?? null) === 'dashboard') {
            return [
                'label' => $section['label'] ?? 'Dashboard',
                'icon' => $section['icon'] ?? null,
                'url' => "/{$path}/dashboard",
                'items' => collect(),
                'groups' => collect(),
                'trailing' => collect(),
            ];
        }

        if (($section['type'] ?? null) === 'link') {
            return [
                'label' => $section['label'] ?? '',
                'icon' => $section['icon'] ?? null,
                'url' => "/{$path}{$section['url']}",
                'items' => collect(),
                'groups' => collect(),
                'trailing' => collect(),
            ];
        }

        $items = collect($section['items'] ?? [])
            ->map(fn (array $item) => static::normalizeItem($item))
            ->filter()
            ->values();

        $groups = collect($section['groups'] ?? [])
            ->map(function (array $group) {
                if (! static::canSee($group['can'] ?? null, $group['sector'] ?? $group['sectors'] ?? null, $group['jurisdiction'] ?? null)) {
                    return null;
                }

                $group['items'] = collect($group['items'] ?? [])
                    ->map(fn (array $item) => static::normalizeItem($item))
                    ->filter()
                    ->values();

                return $group;
            })
            ->filter()
            ->values();

        $trailing = collect($section['trailing'] ?? [])
            ->map(fn (array $item) => static::normalizeItem($item))
            ->filter()
            ->values();

        return [
            'label' => $section['label'] ?? '',
            'icon' => $section['icon'] ?? null,
            'url' => $section['url'] ?? null,
            'items' => $items,
            'groups' => $groups,
            'trailing' => $trailing,
        ];
    }

    protected static function normalizeItem(array $item): ?array
    {
        if (! static::canSee($item['can'] ?? null, $item['sector'] ?? $item['sectors'] ?? null, $item['jurisdiction'] ?? null)) {
            return null;
        }

        $path = trim(config('admin.path'), '/');

        $url = match ($item['type'] ?? null) {
            'dashboard' => "/{$path}/dashboard",
            'resource' => "/{$path}/resources/{$item['key']}",
            'link' => "/{$path}{$item['url']}",
            default => $item['url'] ?? '#',
        };

        $label = $item['label'] ?? null;

        if ($label === null && ($item['type'] ?? null) === 'resource') {
            $class = ResourceRegistry::find((string) ($item['key'] ?? ''));

            $label = $class
                ? $class::pluralLabel()
                : Str::title(Str::replace('-', ' ', (string) ($item['key'] ?? '')));
        }

        return [
            'label' => $label,
            'url' => $url,
            'icon' => $item['icon'] ?? null,
        ];
    }

    /**
     * Menu keys that map to the coarse group permissions on user groups.
     */
    protected const PERMISSION_MAP = [
        'companies' => 'Company Settings',
        'general_settings' => 'Company Settings',
        'offices' => 'Offices',
        'employees' => 'Employees',
        'users' => 'Users',
        'departments' => 'Departments',
        'devices' => 'Devices',
        'holidays' => 'Holidays',
    ];

    /**
     * @param  string|array<int, string>|null  $sector
     */
    protected static function canSee(?string $key, string|array|null $sector = null, string|array|null $jurisdiction = null): bool
    {
        if (! static::sectorAllows($sector)) {
            return false;
        }

        if (! static::jurisdictionAllows($jurisdiction)) {
            return false;
        }

        if ($key === null) {
            return true;
        }

        if (in_array($key, config('admin.hidden_menu', []), true)) {
            return false;
        }

        $user = Auth::user();

        if (! $user || $user->owner || config('admin.allow_all', false)) {
            return true;
        }

        if ($key === 'companies' && $user->ownsAnyCompany()) {
            return true;
        }

        $permission = static::PERMISSION_MAP[$key] ?? null;

        if ($permission === null) {
            return true;
        }

        return ! empty($user->groupForTenant()?->permissions[$permission]);
    }

    /**
     * @param  string|array<int, string>|null  $jurisdiction
     */
    protected static function jurisdictionAllows(string|array|null $jurisdiction): bool
    {
        if ($jurisdiction === null) {
            return true;
        }

        $current = Installation::jurisdiction()->code();
        $allowed = array_map('strval', (array) $jurisdiction);

        foreach ($allowed as $candidate) {
            if (strcasecmp($candidate, $current) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string|array<int, string>|null  $sector
     */
    protected static function sectorAllows(string|array|null $sector): bool
    {
        if ($sector === null) {
            return true;
        }

        $current = app(SectorManager::class)->currentSector();
        $allowed = array_map('strval', (array) $sector);

        foreach ($allowed as $candidate) {
            if (strcasecmp($candidate, $current->value) === 0) {
                return true;
            }
        }

        return false;
    }
}
