<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payroll Calculators | OpenHR PH</title>
    <meta name="description" content="Free Philippine withholding tax and take-home pay calculators based on the BIR TRAIN law, SSS, PhilHealth and Pag-IBIG tables.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-navy-950 dark:text-slate-100">
    <div class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-x-0 -top-40 -z-10 h-[32rem] bg-gradient-to-b from-teal-100/70 via-slate-50 to-slate-50 blur-2xl dark:from-teal-950/40 dark:via-navy-950 dark:to-navy-950"></div>

        <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-6 sm:px-6 lg:px-8">
            <a href="/" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-navy-900 text-sm font-black text-white shadow-sm dark:bg-teal-500 dark:text-navy-950">HR</span>
                <span>
                    <span class="block text-base font-bold tracking-tight text-navy-900 dark:text-white">OpenHR PH</span>
                    <span class="block text-xs font-medium text-slate-500 dark:text-slate-400">Payroll tools</span>
                </span>
            </a>

            <div class="flex items-center gap-2">
                <a href="{{ rtrim(config('demo.url'), '/') }}/register" class="ui-btn-secondary hidden sm:inline-flex">
                    Try the live demo
                </a>

                <button type="button" data-theme-toggle class="ui-btn-secondary" aria-label="Toggle dark mode">
                    <svg data-theme-icon="light" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                    <span class="hidden sm:inline">Theme</span>
                </button>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6 lg:px-8">
            <section class="mx-auto max-w-3xl py-8 text-center sm:py-12">
                <span class="ui-badge bg-teal-100 text-teal-800 dark:bg-teal-500/15 dark:text-teal-300">Updated for 2025 statutory tables</span>
                <h1 class="mt-4 text-3xl font-black tracking-tight text-navy-900 sm:text-4xl lg:text-5xl dark:text-white">
                    Know your numbers before payday.
                </h1>
                <p class="mt-4 text-base leading-relaxed text-slate-600 sm:text-lg dark:text-slate-300">
                    Estimate your Philippine withholding tax and take-home pay using the BIR TRAIN law brackets,
                    the 2025 SSS or GSIS schedules, and current PhilHealth and Pag-IBIG rates.
                </p>
            </section>

            <div class="grid gap-6 xl:grid-cols-2">
                {{-- Withholding tax calculator --}}
                <section class="ui-form-card flex flex-col" aria-labelledby="tax-calculator-title">
                    <div class="ui-form-card-header flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-navy-100 text-navy-800 dark:bg-navy-800 dark:text-teal-300">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </span>
                        <div>
                            <h2 id="tax-calculator-title" class="text-lg font-bold text-navy-900 dark:text-white">Withholding Tax Calculator</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">BIR TRAIN law brackets for any payroll period. 13th month pay and other benefits are exempt up to &#8369;90,000.</p>
                        </div>
                    </div>

                    <form id="tax-calculator-form" class="ui-form-card-body flex-1" novalidate>
                        <div class="sm:col-span-6">
                            <label class="ui-label" for="tax-period">Payroll period <span class="ui-required" aria-hidden="true">*</span></label>
                            <select id="tax-period" name="period" class="ui-select mt-1.5" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="semi-monthly">Semi-monthly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>

                        <fieldset class="sm:col-span-6">
                            <legend class="text-sm font-bold text-navy-900 dark:text-white">Regular compensation</legend>
                            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="ui-label" for="tax-basic-salary">Basic salary</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-basic-salary" name="basic_salary" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-representation">Representation allowance</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-representation" name="representation_allowance" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-transportation">Transportation allowance</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-transportation" name="transportation_allowance" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-cola">Cost of living allowance</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-cola" name="cost_of_living_allowance" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-housing">Fixed housing allowance</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-housing" name="fixed_housing_allowance" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-other-regular">Other taxable regular</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-other-regular" name="other_taxable_regular" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="sm:col-span-6">
                            <legend class="text-sm font-bold text-navy-900 dark:text-white">Supplementary compensation</legend>
                            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="ui-label" for="tax-commission">Commission</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-commission" name="commission" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-profit-sharing">Profit sharing</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-profit-sharing" name="profit_sharing" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-fees">Fees including director's fee</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-fees" name="fees" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-thirteenth-month">13th month pay &amp; other benefits</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-thirteenth-month" name="thirteenth_month" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                    <p class="ui-help">The first &#8369;90,000 is non-taxable; any excess is added to taxable income.</p>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-hazard">Hazard pay</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-hazard" name="hazard_pay" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="tax-overtime">Overtime pay</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-overtime" name="overtime_pay" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="ui-label" for="tax-other-supplementary">Other taxable supplementary</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="tax-other-supplementary" name="other_taxable_supplementary" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <div class="sm:col-span-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <button type="submit" class="ui-btn-primary sm:w-auto" data-submit>
                                Calculate tax
                            </button>
                            <button type="reset" class="ui-btn-ghost sm:w-auto">Clear</button>
                            <p class="text-xs text-slate-500 sm:ml-auto dark:text-slate-400">Runs on secure server-side calculators.</p>
                        </div>
                    </form>

                    <div id="tax-calculator-error" class="ui-error mx-5 mb-5 hidden sm:mx-6" role="alert"></div>

                    <div id="tax-calculator-result" class="hidden border-t border-slate-200 bg-slate-50/70 px-5 py-6 sm:px-6 dark:border-navy-800 dark:bg-navy-950/40" aria-live="polite">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Withholding tax due</p>
                        <p id="tax-result-withholding" class="mt-1 text-3xl font-black tracking-tight text-navy-900 dark:text-white">&#8369;0.00</p>
                        <p id="tax-result-summary" class="mt-1 text-sm text-slate-500 dark:text-slate-400"></p>

                        <dl class="mt-5 divide-y divide-slate-200 text-sm dark:divide-navy-800">
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Regular compensation</dt>
                                <dd id="tax-result-regular" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Supplementary compensation</dt>
                                <dd id="tax-result-supplementary" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Gross compensation income</dt>
                                <dd id="tax-result-gross" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Non-taxable 13th month &amp; benefits</dt>
                                <dd id="tax-result-non-taxable" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Taxable compensation income</dt>
                                <dd id="tax-result-taxable" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Effective tax rate</dt>
                                <dd id="tax-result-rate" class="font-semibold text-slate-900 dark:text-white">0.00%</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                {{-- Take-home pay calculator --}}
                <section class="ui-form-card flex flex-col" aria-labelledby="take-home-title">
                    <div class="ui-form-card-header flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-800 dark:bg-teal-500/15 dark:text-teal-300">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                            </svg>
                        </span>
                        <div>
                            <h2 id="take-home-title" class="text-lg font-bold text-navy-900 dark:text-white">Take-Home Pay Calculator</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Same BIR inputs as the tax calculator, with SSS or GSIS, PhilHealth and Pag-IBIG deducted before net pay.</p>
                        </div>
                    </div>

                    <form id="take-home-form" class="ui-form-card-body flex-1" novalidate>
                        <div class="sm:col-span-3">
                            <label class="ui-label" for="take-home-sector">Sector <span class="ui-required" aria-hidden="true">*</span></label>
                            <select id="take-home-sector" name="sector" class="ui-select mt-1.5" required>
                                <option value="Private" selected>Private (SSS)</option>
                                <option value="Government">Government (GSIS)</option>
                            </select>
                            <p class="ui-help">PhilHealth and Pag-IBIG apply to both sectors.</p>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="ui-label" for="take-home-period">Payroll period <span class="ui-required" aria-hidden="true">*</span></label>
                            <select id="take-home-period" name="period" class="ui-select mt-1.5" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="semi-monthly">Semi-monthly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>

                        <fieldset class="sm:col-span-6">
                            <legend class="text-sm font-bold text-navy-900 dark:text-white">Regular compensation</legend>
                            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="ui-label" for="take-home-basic-salary">Basic salary</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-basic-salary" name="basic_salary" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                    <p class="ui-help">Statutory contributions are based on the monthly equivalent of this amount.</p>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-representation">Representation allowance</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-representation" name="representation_allowance" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-transportation">Transportation allowance</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-transportation" name="transportation_allowance" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-cola">Cost of living allowance</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-cola" name="cost_of_living_allowance" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-housing">Fixed housing allowance</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-housing" name="fixed_housing_allowance" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-other-regular">Other taxable regular</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-other-regular" name="other_taxable_regular" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="sm:col-span-6">
                            <legend class="text-sm font-bold text-navy-900 dark:text-white">Supplementary compensation</legend>
                            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="ui-label" for="take-home-commission">Commission</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-commission" name="commission" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-profit-sharing">Profit sharing</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-profit-sharing" name="profit_sharing" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-fees">Fees including director's fee</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-fees" name="fees" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-thirteenth-month">13th month pay &amp; other benefits</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-thirteenth-month" name="thirteenth_month" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                    <p class="ui-help">The first &#8369;90,000 is non-taxable and is still included in gross pay.</p>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-hazard">Hazard pay</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-hazard" name="hazard_pay" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label" for="take-home-overtime">Overtime pay</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-overtime" name="overtime_pay" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="ui-label" for="take-home-other-supplementary">Other taxable supplementary</label>
                                    <div class="ui-input-group mt-1.5">
                                        <span class="ui-input-addon">&#8369;</span>
                                        <input id="take-home-other-supplementary" name="other_taxable_supplementary" type="text" inputmode="decimal" autocomplete="off" class="ui-input" placeholder="0.00" data-amount>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <div class="sm:col-span-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <button type="submit" class="ui-btn-primary sm:w-auto" data-submit>
                                Calculate net pay
                            </button>
                            <button type="reset" class="ui-btn-ghost sm:w-auto">Clear</button>
                            <p class="text-xs text-slate-500 sm:ml-auto dark:text-slate-400">Monthly contributions prorated to the selected period.</p>
                        </div>
                    </form>

                    <div id="take-home-error" class="ui-error mx-5 mb-5 hidden sm:mx-6" role="alert"></div>

                    <div id="take-home-result" class="hidden border-t border-slate-200 bg-slate-50/70 px-5 py-6 sm:px-6 dark:border-navy-800 dark:bg-navy-950/40" aria-live="polite">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Estimated net pay</p>
                                <p id="take-home-net" class="mt-1 text-3xl font-black tracking-tight text-teal-700 dark:text-teal-300">&#8369;0.00</p>
                                <p id="take-home-period-label" class="mt-1 text-sm text-slate-500 dark:text-slate-400"></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Gross pay</p>
                                <p id="take-home-gross" class="mt-1 text-lg font-bold text-navy-900 dark:text-white">&#8369;0.00</p>
                            </div>
                        </div>

                        <h3 class="mt-6 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Compensation</h3>
                        <dl class="mt-2 divide-y divide-slate-200 text-sm dark:divide-navy-800">
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Regular compensation</dt>
                                <dd id="take-home-regular" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Supplementary compensation</dt>
                                <dd id="take-home-supplementary" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Non-taxable 13th month &amp; benefits</dt>
                                <dd id="take-home-non-taxable" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Taxable compensation income</dt>
                                <dd id="take-home-taxable" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                        </dl>

                        <h3 class="mt-6 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Employee deductions</h3>
                        <dl class="mt-2 divide-y divide-slate-200 text-sm dark:divide-navy-800">
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt id="take-home-social-insurance-label" class="text-slate-600 dark:text-slate-300">SSS (employee share)</dt>
                                <dd id="take-home-social-insurance" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">PhilHealth (employee share)</dt>
                                <dd id="take-home-philhealth" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Pag-IBIG (employee share)</dt>
                                <dd id="take-home-pagibig" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Withholding tax</dt>
                                <dd id="take-home-tax" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="font-semibold text-slate-700 dark:text-slate-200">Total deductions</dt>
                                <dd id="take-home-total-deductions" class="font-bold text-navy-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                        </dl>

                        <h3 class="mt-6 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Employer contributions</h3>
                        <dl class="mt-2 divide-y divide-slate-200 text-sm dark:divide-navy-800">
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt id="take-home-social-insurance-employer-label" class="text-slate-600 dark:text-slate-300">SSS (employer share)</dt>
                                <dd id="take-home-social-insurance-employer" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">PhilHealth (employer share)</dt>
                                <dd id="take-home-philhealth-employer" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="text-slate-600 dark:text-slate-300">Pag-IBIG (employer share)</dt>
                                <dd id="take-home-pagibig-employer" class="font-semibold text-slate-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2.5">
                                <dt class="font-semibold text-slate-700 dark:text-slate-200">Total employer cost</dt>
                                <dd id="take-home-employer-cost" class="font-bold text-navy-900 dark:text-white">&#8369;0.00</dd>
                            </div>
                        </dl>
                    </div>
                </section>
            </div>

            <section class="mx-auto mt-10 max-w-3xl rounded-card border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-relaxed text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                <p><strong>Estimate only.</strong> Results use published BIR, SSS, GSIS, PhilHealth and Pag-IBIG tables and do not include company-specific rules, de minimis benefits, or other adjustments. Verify against official agency issuances before using for payroll.</p>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white/60 py-8 dark:border-navy-800 dark:bg-navy-950/60">
            <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-3 px-4 text-sm text-slate-500 sm:flex-row sm:px-6 lg:px-8 dark:text-slate-400">
                <p>&copy; {{ date('Y') }} OpenHR PH. Open-source Philippine payroll.</p>
                <p>BIR TRAIN law &middot; SSS/GSIS &middot; PhilHealth 5% &middot; Pag-IBIG 2%</p>
            </div>
        </footer>
    </div>
</body>
</html>
