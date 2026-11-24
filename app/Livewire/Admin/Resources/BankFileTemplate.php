<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\BankFileTemplate as BankFileTemplateModel;
use App\Services\Payroll\BankFileGenerator;
use Closure;
use Illuminate\Http\Request;

class BankFileTemplate extends Resource
{
    public static string $model = BankFileTemplateModel::class;

    public static string $title = 'name';

    public static string $group = 'Payroll';

    public static array $search = ['name', 'bank_name'];

    public static function label(): string
    {
        return 'Bank File Template';
    }

    public static function pluralLabel(): string
    {
        return 'Bank File Templates';
    }

    public function fields(Request $request): array
    {
        $sources = implode(', ', array_keys(BankFileGenerator::SOURCES));

        return [
            ID::make()->sortable(),

            Text::make('Template name', 'name')
                ->rules('required', 'max:128')
                ->sortable(),

            Text::make('Bank', 'bank_name')
                ->rules('nullable', 'max:128')
                ->placeholder('e.g. Land Bank of the Philippines'),

            Text::make('Delimiter', 'delimiter')
                ->default(',')
                ->rules('required', 'max:4')
                ->help('Usually a comma or a pipe (|).'),

            Textarea::make('Columns (JSON)', 'columns')
                ->rules('required', 'json', $this->columnsRule())
                ->resolveUsing(fn ($value): string => $this->encode($value))
                ->fillUsing(function ($model, string $attribute, mixed $value): void {
                    $model->setAttribute($attribute, json_decode((string) $value, true) ?: []);
                })
                ->help('Array of {"source":"…","label":"…"}. Allowed sources: '.$sources),

            Textarea::make('Header rows (JSON)', 'header_rows')
                ->rules('nullable', 'json')
                ->resolveUsing(fn ($value): string => $this->encode($value))
                ->fillUsing(function ($model, string $attribute, mixed $value): void {
                    $decoded = json_decode((string) $value, true);
                    $model->setAttribute($attribute, is_array($decoded) ? $decoded : null);
                })
                ->help('Optional rows before the column header. Tokens: {{company_name}}, {{company_code}}, {{company_account}}, {{company_bank}}, {{payment_date}}, {{payroll_date}}, {{reference}}, {{period_from}}, {{period_to}}. Leave blank to use the column labels.'),

            Textarea::make('Footer rows (JSON)', 'footer_rows')
                ->rules('nullable', 'json')
                ->resolveUsing(fn ($value): string => $this->encode($value))
                ->fillUsing(function (BankFileTemplateModel $model, string $attribute, mixed $value): void {
                    $decoded = json_decode((string) $value, true);
                    $model->setAttribute($attribute, is_array($decoded) ? $decoded : null);
                })
                ->help('Optional trailer rows. Leave blank to write the default TOTAL row.'),

            Boolean::make('Amounts in centavos', 'amount_in_centavos')
                ->default(false)
                ->help('Leave off for a decimal amount such as 12345.67.'),

            Boolean::make('Include control total', 'include_control_total')
                ->default(true),

            Text::make('Date format', 'date_format')
                ->default('Ymd')
                ->rules('required', 'max:16'),

            Boolean::make('Active', 'is_active')
                ->default(true),
        ];
    }

    private function columnsRule(): Closure
    {
        $allowed = array_keys(BankFileGenerator::SOURCES);

        return function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
            $columns = json_decode((string) $value, true);

            if (! is_array($columns) || $columns === []) {
                $fail('The columns must be a non-empty JSON array.');

                return;
            }

            foreach ($columns as $column) {
                if (! is_array($column) || ! isset($column['source'])) {
                    $fail('Each column needs a source.');

                    return;
                }

                if (! in_array((string) $column['source'], $allowed, true)) {
                    $fail('Unsupported column source: '.$column['source']);

                    return;
                }
            }
        };
    }

    private function encode(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
