<?php

namespace App\Livewire\Admin\Settings;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Fields\File;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Models\Setting;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;

class Settings extends Component
{
    use WithFileUploads;

    public string $page = 'general';

    public array $form = [];

    public function mount(string $page = 'general'): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);

        if (! array_key_exists($page, self::pages())) {
            abort(404);
        }

        $this->page = $page;

        $this->initializeForm();
    }

    public function render()
    {
        return view('livewire.admin.settings', [
            'fields' => $this->fields(),
            'pages' => self::pages(),
            'pageTitle' => self::pages()[$this->page],
        ])->layout('layouts.admin')->title(self::pages()[$this->page].' Settings');
    }

    public function save(): void
    {
        $fields = $this->fields();

        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            if ($this->isFileField($field) && ! $this->hasUploadedFile($field)) {
                continue;
            }

            $fieldRules = $field->validationRules(false);

            if (! empty($fieldRules)) {
                $key = 'form.'.$field->attribute;
                $rules[$key] = $fieldRules;
                $attributes[$key] = $field->name ?: $field->attribute;
            }
        }

        if (! empty($rules)) {
            $this->validate($rules, [], $attributes);
        }

        foreach ($fields as $field) {
            $attribute = $field->attribute;

            if (! array_key_exists($attribute, $this->form)) {
                continue;
            }

            $value = $this->form[$attribute];

            if ($this->isFileField($field)) {
                if ($value instanceof TemporaryUploadedFile) {
                    $value = $value->store('settings', 'public');
                } elseif ($value === null || $value === '') {
                    continue;
                }
            }

            // The table key is only unique per company, so resolve the row by id
            // (the last row wins, matching the value shown on the page).
            $setting = Setting::query()->where('key', $attribute)->orderByDesc('id')->first()
                ?? new Setting(['key' => $attribute]);

            $setting->value = $value;
            $setting->save();
        }

        session()->flash('admin.saved', 'Settings saved.');

        $this->redirectRoute('admin.settings', ['page' => $this->page]);
    }

    /**
     * The list of settings pages exposed by this component.
     */
    public static function pages(): array
    {
        return [
            'general' => 'General',
            'attendance' => 'Attendance',
            'payroll' => 'Payroll',
            'leave' => 'Leave',
        ];
    }

    /**
     * The field definitions for the current settings page.
     *
     * @return array<int, Field>
     */
    protected function fields(): array
    {
        return match ($this->page) {
            'attendance' => $this->attendanceFields(),
            'payroll' => $this->payrollFields(),
            'leave' => $this->leaveFields(),
            default => $this->generalFields(),
        };
    }

    /**
     * @return array<int, Field>
     */
    protected function generalFields(): array
    {
        return [
            File::make('Logo', 'logo'),

            Text::make('Company Name', 'company_name'),

            Text::make('Trade Name', 'trade_name')
                ->rules('required'),

            Select::make('Type')
                ->options([
                    'Private' => 'Private',
                    'Government' => 'Government',
                    'Non-Profit' => 'Non-Profit',
                ]),

            Text::make('TIN', 'tin')
                ->placeholder('000-000-000-000'),

            Text::make('RDO', 'rdo')
                ->placeholder('049'),

            Text::make('SSS', 'sss')
                ->placeholder('00-0000000-0'),

            Text::make('HDMF', 'hdmf')
                ->placeholder('0000-0000-0000'),

            Text::make('PhilHealth', 'phil_health')
                ->placeholder('00-000000000-0'),

            Boolean::make('Automatically generate Employee Number', 'auto_employee_number')
                ->help('Automatically generate employee number when adding new employee')
                ->default(false),
        ];
    }

    /**
     * @return array<int, Field>
     */
    protected function attendanceFields(): array
    {
        return [
            Number::make('Maximum Pairing hours', 'maximum_pairing_range')
                ->help('The maximum number of hours that the system will pair in number of hours')
                ->placeholder('Ex: 20')
                ->rules('numeric', 'max:24', 'min:1'),

            Number::make('Minimum Pairing range', 'minimum_pairing_range')
                ->help('The minimum number of minutes that the system will pair.')
                ->placeholder('Ex: 5')
                ->rules('numeric', 'max:24', 'min:0'),

            Text::make('Night Shift Differential Start', 'night_shift_differential_start')
                ->help('Night shift differential pay is an additional pay for work between 6:00 p.m. and 6:00 a.m. the following day for those in the Government Service, and between 10:00 p.m. and 6:00 a.m. the following day for those in the private sector.')
                ->default('22:00')
                ->placeholder('Ex: 22:00')
                ->withMeta([
                    'extraAttributes' => ['type' => 'time'],
                ])
                ->rules('required'),

            Text::make('Night Shift Differential End', 'night_shift_differential_end')
                ->placeholder('Ex: 06:00')
                ->withMeta([
                    'extraAttributes' => ['type' => 'time'],
                ])
                ->rules('required'),

            Boolean::make('Automatically mark absent', 'auto_absent_no_timesheet')
                ->help('Automatically mark absent if no timesheet entry')
                ->default(false),
        ];
    }

    /**
     * @return array<int, Field>
     */
    protected function payrollFields(): array
    {
        return [
            Number::make('Total number of days in a year', 'days_year_no_weeked')
                ->help('Daily Paid Monday to Friday (No weekends). DOLE uses 261 working days.')
                ->default(261)
                ->placeholder('Ex: 261')
                ->rules('numeric', 'max:265', 'min:1'),

            Select::make('SSS payment schedule for semi-monthly', 'sss_schedule')
                ->rules('required')
                ->options(payment_schedules()),

            Select::make('Philhealth payment schedule for semi-monthly', 'phil_health_schedule')
                ->rules('required')
                ->options(payment_schedules()),

            Select::make('HDMF payment schedule for semi-monthly', 'hdmf_schedule')
                ->rules('required')
                ->options(payment_schedules()),

            Number::make('Total number of days in a year', 'days_year_with_weeked')
                ->help('Daily Paid Monday to Saturday (With weekends). DOLE uses 313 (365 days minus 52 Sundays).')
                ->default(313)
                ->placeholder('Ex: 313')
                ->rules('numeric', 'max:366', 'min:1'),

            Boolean::make('Deduct Withholding Tax', 'deduct_tax')
                ->help('If you want to deduct Withholding then check this')
                ->default(true),

            Boolean::make('Automatically compute overtime', 'auto_compute_overtime')
                ->help('If you want to automatically compute overtime on timesheet.')
                ->default(true),

            Number::make('Minutes qualified for overtime', 'minutes_qualified_overtime')
                ->default(60)
                ->placeholder('Ex: 60')
                ->rules('numeric'),

            Boolean::make('Automatically compute night differential', 'auto_compute_nt')
                ->help('If you want to automatically compute night differential on timesheet.')
                ->default(true),

            File::make('Payslip Logo', 'payslip_logo')
                ->help('max width=400px, height=100px')
                ->rules('dimensions:max_width=400,height=100'),

            Boolean::make('Display logo in payslip', 'display_logo_payslip')
                ->default(false),
        ];
    }

    /**
     * @return array<int, Field>
     */
    protected function leaveFields(): array
    {
        return [];
    }

    protected function initializeForm(): void
    {
        $fields = $this->fields();

        $values = Setting::query()
            ->whereIn('key', collect($fields)->pluck('attribute')->filter()->all())
            ->orderBy('id')
            ->get(['key', 'value'])
            ->keyBy('key')
            ->map(fn (Setting $setting) => $setting->value)
            ->toArray();

        foreach ($fields as $field) {
            $this->form[$field->attribute] = array_key_exists($field->attribute, $values)
                ? $values[$field->attribute]
                : $field->default;
        }
    }

    protected function isFileField(Field $field): bool
    {
        return $field->component === 'file';
    }

    protected function hasUploadedFile(Field $field): bool
    {
        return ($this->form[$field->attribute] ?? null) instanceof TemporaryUploadedFile;
    }
}
