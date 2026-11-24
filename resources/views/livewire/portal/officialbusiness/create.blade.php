<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ 'Official Business Application' }}</h3>
        <!-- <div class="mt-3 sm:mt-0 sm:ml-4">
    <button type="button" class="ui-btn-primary">Add</button>
  </div> -->
    </div>
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
                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Date From</label>
                            <input wire:model.live="dateFrom" type="date" id="from" class="ui-input mt-1">
                            @error('dateFrom') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="to" class="ui-label">Date To</label>
                            <input wire:model.live="dateTo" type="date" id="from" class="ui-input mt-1">
                            @error('dateTo') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="timeFrom" class="ui-label">Time From</label>
                            <input wire:model.live="timeFrom" type="time" class="ui-input mt-1">
                            @error('timeFrom') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror

                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="timeTo" class="ui-label">Time To</label>
                            <input wire:model.live="timeTo" type="time" class="ui-input mt-1">
                            @error('timeTo') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="location" class="ui-label">Location</label>
                            <input wire:model.live="location" type="text" class="ui-input mt-1">
                            @error('location') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="reason" class="ui-label">Purpose</label>
                            <input wire:model.live="purpose" type="text" class="ui-input mt-1">
                            @error('purpose') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Attach supporting document</label>
                            <input wire:model.live="attachments" type="file" class="ui-input mt-1">
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('portal.officialbusiness') }}" wire:navigate class="rounded-md border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-900 py-2 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 shadow-sm hover:bg-slate-50 dark:bg-navy-800/40 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="store" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-teal-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Submit</button>
        </div>
    </form>
</div>