<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\CompanyBank as CompanyBankModel;
use Illuminate\Http\Request;

class CompanyBank extends Resource
{
    public static string $model = CompanyBankModel::class;

    public static string $title = 'name';

    public static string $group = 'Admin';

    public static array $search = ['name', 'code', 'account_number'];

    public static function label(): string
    {
        return 'Payroll Account';
    }

    public static function pluralLabel(): string
    {
        return 'Payroll Accounts';
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Bank Name', 'name')
                ->rules('required', 'max:64')
                ->sortable()
                ->placeholder('e.g. Land Bank of the Philippines'),

            Text::make('Bank Code', 'code')
                ->rules('nullable', 'max:64')
                ->placeholder('e.g. LBP'),

            Text::make('Account Number', 'account_number')
                ->rules('required', 'max:32')
                ->placeholder('Company debit account number'),
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
