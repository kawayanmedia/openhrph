<!-- This example requires Tailwind CSS v2.0+ -->
<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ 'Schedule Adjustment Application' }}</h3>
        <!-- <div class="mt-3 sm:mt-0 sm:ml-4">
    <button type="button" class="ui-btn-primary">Add</button>
  </div> -->
    </div>



    <!--
  This example requires Tailwind CSS v2.0+ 
  
  This example requires some changes to your config:
  
  ```
  // tailwind.config.js
  module.exports = {
    // ...
    plugins: [
      // ...
      require('@tailwindcss/forms'),
    ],
  }
  ```
-->

    <form class="space-y-6" wire:submit="submit">

        <div class="ui-card p-5 sm:p-6">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">Work Schedule</h3>
                    <!-- <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Payroll Type</p>

                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Schedule Type</p>

                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Total Expected Hours</p> -->

                    <div class="grid grid-rows-3 grid-flow-col gap-3 pt-2">

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="ui-label">Payroll Type</label>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Semi-Montly</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="ui-label">Schedule Type</label>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Normal Shift</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="ui-label">Total Expected Hours</label>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">9</p>
                        </div>

                    </div>


                </div>
                <div class="mt-5 md:col-span-2 md:mt-0">
                    <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">Effective Date</h3>
                    <div class="grid grid-cols-6 gap-6 pt-2">
                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">From</label>
                            <input wire:model.live="from" type="date" id="from" class="ui-input mt-1">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="to" class="ui-label">To</label>
                            <input wire:model.live="to" type="date" id="to" class="ui-input mt-1">
                        </div>



                        <div class="col-span-6 sm:col-span-3">
                            <label for="shiftStart" class="ui-label">Shift Start</label>
                            <input wire:model.live="shiftStart" type="time" class="ui-input mt-1">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="breakStart" class="ui-label">Break Start</label>
                            <input wire:model.live="breakStart" type="time" class="ui-input mt-1">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="breakEnd" class="ui-label">Break End</label>
                            <input wire:model.live="breakEnd" type="time" class="ui-input mt-1">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="shiftEnd" class="ui-label">Shift End</label>
                            <input wire:model.live="shiftEnd" type="time" class="ui-input mt-1">
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('portal.schedules') }}" wire:navigate class="rounded-md border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-900 py-2 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 shadow-sm hover:bg-slate-50 dark:bg-navy-800/40 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="store" type="submit" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-teal-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Proceed</button>
        </div>
    </form>
</div>