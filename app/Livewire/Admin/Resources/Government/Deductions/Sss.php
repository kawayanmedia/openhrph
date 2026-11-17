<?php

namespace App\Livewire\Admin\Resources\Government\Deductions;

use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use Illuminate\Http\Request;

class Sss extends Resource
{
    public static string $model = \App\Services\Government\Models\Sss::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Government';

    public static array $search = ['id'];

    public static function uriKey(): string
    {
        return 'sss';
    }

    public static function label(): string
    {
        return 'SSS Contribution';
    }

    public function title(): string
    {
        return (string) ($this->resource?->monthl_basic ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Currency::make('From', 'from')->currency(''),
            Currency::make('To', 'to')->currency(''),
            Currency::make('ee Share', 'regular_ee')->currency(''),
            Currency::make('ee WISP/Provident', 'wisp_ee')->currency(''),
            Text::make('Total ee', function () {
                return number_format($this->regular_ee + $this->wisp_ee, 2);
            }),
            Currency::make('er Share', 'regular_er')->currency(''),
            Currency::make('er WISP/Provident', 'wisp_er')->currency(''),
            Currency::make('er EC-ER', 'ecc')->currency(''),
            Text::make('Total er', function () {
                return number_format($this->regular_er + $this->wisp_er + $this->ecc, 2);
            }),
        ];
    }

    public function cards(Request $request): array
    {
        return [];
    }

    public function filters(Request $request): array
    {
        return [];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [];
    }
}
