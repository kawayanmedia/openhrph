<?php

namespace App\Livewire\Admin\Settings;

use App\Jurisdictions\Support\JurisdictionManager;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Fields\File;
use App\Livewire\Admin\Fields\Heading;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Models\Payroll;
use App\Models\Setting;
use App\Support\Installation\Installation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

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

            if (
                ! array_key_exists($attribute, $this->form)
                || $field->component === 'heading'
                || str_starts_with($attribute, 'installation_')
            ) {
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

        if ($this->page === 'statutory' && ! $this->saveInstallationSettings()) {
            return;
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
            'statutory' => 'Country & statutory',
            'sector' => 'Sector',
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
            'statutory' => $this->statutoryFields(),
            'sector' => $this->sectorFields(),
            'leave' => $this->leaveFields(),
            default => $this->generalFields(),
        };
    }

    /**
     * @return array<int, Field>
     */
    /**
     * @return array<int, Field>
     */
    protected function companyIdentifierFields(): array
    {
        $sector = Installation::sector();
        $fields = [];

        foreach (Installation::jurisdiction()->identifiers()->companyFields() as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                continue;
            }

            if ($sector !== null && ! empty($field['sector']) && strcasecmp((string) $field['sector'], $sector) !== 0) {
                continue;
            }

            $fields[] = Text::make((string) ($field['label'] ?? $key), $key);
        }

        return $fields;
    }

    protected function generalFields(): array
    {
        return [
            File::make('Logo', 'logo'),

            Text::make('Company Name', 'company_name'),

            Text::make('Trade Name', 'trade_name')
                ->rules('required'),

            ...(Installation::sector() !== null
                ? [
                    Heading::make('Sector: '.Installation::sector())
                        ->help('Locked for the whole installation. Use `php artisan openhr:install --force` to change it.'),
                ]
                : [
                    Select::make('Type')
                        ->options([
                            'Private' => 'Private',
                            'Government' => 'Government',
                            'Non-Profit' => 'Non-Profit',
                        ]),
                ]),

            ...$this->companyIdentifierFields(),

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
    protected function statutoryFields(): array
    {
        $profile = Installation::countryProfile();
        $sector = Installation::sector() ?? 'Not selected';
        $jurisdiction = Installation::jurisdiction();

        return [
            Heading::make(
                'Installation: '.$sector.' · Country: '.$jurisdiction->name().' ('.$jurisdiction->code().') · Driver: '.$jurisdiction->statutoryDriver()->code(),
            )->help(
                'The sector is fixed for the whole installation. Change it, or the country, with `php artisan openhr:install --force`.',
            ),

            Select::make('Country', 'installation_country_code')
                ->options($this->jurisdictionOptions())
                ->default(Installation::countryCode())
                ->rules('required'),

            Text::make('Currency code', 'installation_currency_code')
                ->default($profile['currency'] ?? 'PHP')
                ->rules('required', 'max:3'),

            Text::make('Locale', 'installation_locale')
                ->default($profile['locale'] ?? 'en')
                ->rules('required', 'max:16'),

            Text::make('Timezone', 'installation_timezone')
                ->default($profile['timezone'] ?? 'UTC')
                ->rules('required', 'max:64'),

            Heading::make('Jurisdiction')
                ->help('The country selects the statutory driver, payroll rules, holidays, leave and reports. A country without a dedicated module reads Statutory components and their bracket rows; import a JSON definition with `php artisan payroll:statutory:import --country=XX --file=path.json`.'),
        ];
    }

    /**
     * Sector-specific defaults. The fields shown depend on the installation
     * sector; the underlying settings keys are shared so payroll reads one
     * shape for either sector.
     *
     * @return array<int, Field>
     */
    protected function sectorFields(): array
    {
        $policy = Installation::sectorPolicy();
        $sector = $policy->sector();
        $defaults = $policy->defaults();
        $features = implode(', ', $policy->features()->enabledNames());

        $fields = [
            Heading::make('Sector: '.$sector->label())
                ->help('Fixed for the whole installation. Change it with `php artisan openhr:install --force`.'),

            Heading::make('Enabled capabilities')
                ->help($features !== '' ? $features : 'None declared for this sector.'),
        ];

        if (Installation::jurisdiction()->statutoryDriver()->code() !== 'ph') {
            $fields[] = Heading::make('Country rules')
                ->help('This jurisdiction supplies its own contribution, tax and leave rules.');
        } elseif ($sector->socialInsuranceAgency() === 'gsis') {
            $fields[] = Text::make('GSIS contribution base', 'gsis_base')
                ->default((string) ($defaults['gsis_base'] ?? 'basic'))
                ->help('basic, gross, or taxable. The rate itself lives in the effective-dated GSIS table.');

            $fields[] = Heading::make('Government bonuses and allowances')
                ->help('PERA, RATA, mid-year bonus, year-end bonus, cash gift, SRI and CNA are in the compensation catalogue. PBB and loyalty cash gift stay inactive until their guidelines are verified.');

            $fields[] = Heading::make('Government reference data')
                ->help('Salary grades, plantilla items, NOSA and service records are government-only screens and are hidden in a private installation.');
        } else {
            $fields[] = Text::make('SSS contribution base', 'sss_base')
                ->default((string) ($defaults['sss_base'] ?? 'basic'))
                ->help('basic, gross, or taxable. The rate itself lives in the effective-dated SSS table.');

            $fields[] = Text::make('PhilHealth contribution base', 'philhealth_base')
                ->default((string) ($defaults['philhealth_base'] ?? 'basic'));

            $fields[] = Text::make('Pag-IBIG contribution base', 'hdmf_base')
                ->default((string) ($defaults['hdmf_base'] ?? 'basic'));

            $fields[] = Heading::make('Private bonuses and leave')
                ->help('13th month pay (PD 851), service incentive leave (Labor Code Art. 95), separation pay and retirement pay are in the catalogues and reports.');
        }

        $fields[] = Text::make('Night differential start', 'night_shift_differential_start')
            ->default((string) ($defaults['night_shift_differential_start'] ?? '22:00'))
            ->withMeta(['extraAttributes' => ['type' => 'time']])
            ->rules('required');

        $fields[] = Text::make('Night differential end', 'night_shift_differential_end')
            ->default((string) ($defaults['night_shift_differential_end'] ?? '06:00'))
            ->withMeta(['extraAttributes' => ['type' => 'time']])
            ->rules('required');

        return $fields;
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

            ...(Installation::jurisdiction()->statutoryDriver()->code() === 'ph' ? [
                Select::make('SSS payment schedule for semi-monthly', 'sss_schedule')
                    ->rules('required')
                    ->options(payment_schedules()),
                Select::make('Philhealth payment schedule for semi-monthly', 'phil_health_schedule')
                    ->rules('required')
                    ->options(payment_schedules()),
                Select::make('HDMF payment schedule for semi-monthly', 'hdmf_schedule')
                    ->rules('required')
                    ->options(payment_schedules()),
            ] : []),

            Number::make('Total number of days in a year', 'days_year_with_weeked')
                ->help('Daily Paid Monday to Saturday (With weekends). DOLE uses 313 (365 days minus 52 Sundays).')
                ->default(313)
                ->placeholder('Ex: 313')
                ->rules('numeric', 'max:366', 'min:1'),

            ...(Installation::jurisdiction()->statutoryDriver()->code() === 'ph' ? [
                Boolean::make('Pay unworked regular holidays', 'pay_unworked_regular_holidays')
                    ->help('Pays 100% of the daily rate for an unworked regular holiday to daily-paid employees who were present or on paid leave on the preceding workday (Labor Code Art. 94). Leave it on unless a collective agreement or agency rule grants a different treatment.')
                    ->default(true),
            ] : []),

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

        foreach ([
            'installation_country_code' => Installation::countryCode(),
            'installation_currency_code' => Installation::currencyCode(),
            'installation_locale' => Installation::locale(),
            'installation_timezone' => Installation::timezone(),
        ] as $attribute => $value) {
            if (array_key_exists($attribute, $this->form)) {
                $this->form[$attribute] = $value;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    protected function jurisdictionOptions(): array
    {
        $options = [];

        foreach ((array) config('payroll.countries', []) as $code => $profile) {
            $options[strtoupper((string) $code)] = $code.' — '.($profile['name'] ?? $code);
        }

        foreach (app(JurisdictionManager::class)->all() as $code => $jurisdiction) {
            $options[$code] = $code.' — '.$jurisdiction->name();
        }

        ksort($options);

        return $options;
    }

    /**
     * Persist the installation-wide settings on the Country & statutory page.
     */
    protected function saveInstallationSettings(): bool
    {
        $country = (string) ($this->form['installation_country_code'] ?? Installation::countryCode()) |> trim(...) |> strtoupper(...);

        if (! array_key_exists($country, $this->jurisdictionOptions())) {
            $this->addError('form.installation_country_code', 'Choose a registered jurisdiction.');

            return false;
        }

        if ($country !== Installation::countryCode() && $this->hasProcessedPayroll()) {
            $this->addError(
                'form.installation_country_code',
                'The country cannot change after a payroll has been processed. Use `php artisan openhr:install --force` if you accept the history risk.',
            );

            return false;
        }

        Installation::setMany([
            Installation::KEY_COUNTRY => $country,
            Installation::KEY_CURRENCY => (string) ($this->form['installation_currency_code'] ?? Installation::currencyCode()) |> trim(...) |> strtoupper(...),
            Installation::KEY_LOCALE => trim((string) ($this->form['installation_locale'] ?? Installation::locale())),
            Installation::KEY_TIMEZONE => trim((string) ($this->form['installation_timezone'] ?? Installation::timezone())),
        ]);

        return true;
    }

    protected function hasProcessedPayroll(): bool
    {
        return Payroll::withoutTenant()
            ->whereIn('status', [Payroll::STATUS_PROCESSED, Payroll::STATUS_APPROVED, Payroll::STATUS_CLOSED])
            ->exists();
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
