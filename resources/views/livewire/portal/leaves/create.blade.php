<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">Leave Application</h3>
    </div>
    <form class="space-y-6" wire:submit="submit">

        <div class="ui-card p-5 sm:p-6">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">Full Name</h3>
                    <p class="mt-1 text-sm font-semibold text-navy-900 dark:text-white">{{ $employee?->fullname ?: 'Employee' }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Available Leave Credits</p>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-navy-900 dark:text-white">{{ $employee?->total_vacation_leave ?? 0 }}</span>
                        <span class="text-slate-600 dark:text-slate-300">Vacation days left</span>
                    </div>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-navy-900 dark:text-white">{{ $employee?->total_sick_leave ?? 0 }}</span>
                        <span class="text-slate-600 dark:text-slate-300">Sick days left</span>
                    </div>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-navy-900 dark:text-white">{{ $employee?->total_personal_leave ?? 0 }}</span>
                        <span class="text-slate-600 dark:text-slate-300">Personal days left</span>
                    </div>

                </div>
                <div class="mt-5 md:col-span-2 md:mt-0">
                    <div class="grid grid-cols-6 gap-6">

                        <!-- <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">First Name</label>
                            <input wire:model.live="from" type="text" id="from" class="ui-input mt-1">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Last Name</label>
                            <input wire:model.live="from" type="text" id="from" class="ui-input mt-1">
                        </div> -->

                        <div class="col-span-6 sm:col-span-6">
                            <label for="country" class="ui-label">Leave Type</label>
                            <select wire:model.live="leaveTypeId" id="country" name="country" autocomplete="country-name" class="ui-select mt-1">
                                @foreach ($leaveOptions as $key => $val )
                                <option value="{{ $key }}">{{ $val }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">From</label>
                            <input wire:model.live="dateFrom" type="date" id="from" class="ui-input mt-1">
                            @error('dateFrom') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="to" class="ui-label">To</label>
                            <input wire:model.live="dateTo" type="date" id="to" class="ui-input mt-1">
                            @error('dateTo') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">With Pay Number of days</label>
                            <input wire:model.live="daysWithPay" readonly type="number" step="0.5" min="0" class="ui-input mt-1">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Without Pay Number of days</label>
                            <input wire:model.live="daysWithoutPay" readonly type="number" step="0.5" min="0" class="ui-input mt-1">
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">File Attachment</label>
                            <input wire:model.live="attachments" type="file" class="ui-input mt-1">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Reason</label>
                            <input wire:model.live="reason" type="text" class="ui-input mt-1">
                            @error('reason') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <!-- <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Date</label>
                            <input wire:model.live="dateApply" type="date" id="from" class="ui-input mt-1">
                        </div> -->

                        <div class="col-span-6 sm:col-span-3">

                            <div>
                                <label for="to" class="ui-label">Options</label>

                                <fieldset class="mt-4">
                                    <legend class="sr-only">Notification method</legend>
                                    <div class="space-y-4 sm:flex sm:items-center sm:space-y-0 sm:space-x-10">
                                        <div class="flex items-center">
                                            <input id="whole" wire:model.live="isWholeDay" value="true" type="radio" checked class="h-4 w-4 border-slate-300 dark:border-navy-700 text-teal-700 dark:text-teal-300 focus:ring-teal-500">
                                            <label for="whole" class="ml-3 ui-label">Whole Day</label>
                                        </div>

                                        <div class="flex items-center">
                                            <input id="half" wire:model.live="isWholeDay" value="false" type="radio" class="h-4 w-4 border-slate-300 dark:border-navy-700 text-teal-700 dark:text-teal-300 focus:ring-teal-500">
                                            <label for="half" class="ml-3 ui-label">Half Day</label>
                                        </div>


                                    </div>
                                </fieldset>
                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('portal.leaves') }}" wire:navigate class="rounded-md border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-900 py-2 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 shadow-sm hover:bg-slate-50 dark:bg-navy-800/40 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="applyLeave" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-teal-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Apply</button>
        </div>
    </form>
</div>