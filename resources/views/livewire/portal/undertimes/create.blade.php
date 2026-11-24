<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ _('Overtime Application') }}</h3>
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

                    <div class="grid grid-rows-3 grid-flow-col gap-3 pt-2">

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="ui-label">Full Name</label>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">_____</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="ui-label">Position</label>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">_____</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="ui-label">Department</label>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">_____</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="ui-label">Date Filed</label>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ now()->toDateString() }}</p>
                        </div>

                    </div>


                </div>
                <div class="mt-5 md:col-span-2 md:mt-0">
                    <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">Effective Date</h3>
                    <div class="grid grid-cols-6 gap-6 pt-2">
                        <div class="col-span-6 sm:col-span-6">
                            <label for="from" class="ui-label">Date</label>
                            <input wire:model.live="date" type="date" id="from" class="ui-input mt-1">
                            @error('date') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">From</label>
                            <input wire:model.live="timeFrom" type="time" class="ui-input mt-1">
                            @error('timeFrom') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="to" class="ui-label">To</label>
                            <input wire:model.live="timeTo" type="time" class="ui-input mt-1">
                            @error('timeTo') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="hours" class="ui-label">Number of Hours</label>
                            <input wire:model.live="hours" type="number" class="ui-input mt-1">
                            @error('hours') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="reason" class="ui-label">Reason</label>
                            <input wire:model.live="reason" type="text" class="ui-input mt-1">
                            @error('reason') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                   

                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('portal.undertimes') }}" wire:navigate class="rounded-md border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-900 py-2 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 shadow-sm hover:bg-slate-50 dark:bg-navy-800/40 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="store" type="submit" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-teal-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Submit</button>
        </div>
    </form>
</div>