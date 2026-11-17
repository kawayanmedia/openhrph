<div class="px-4 py-5 sm:p-6">
    <div class="border-b border-gray-200 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-medium leading-6 text-gray-900">Leave Application</h3>
    </div>
    <form class="space-y-6" wire:submit="submit">

        <div class="bg-white px-4 py-5 shadow sm:rounded-lg sm:p-6">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Full Name</h3>
                    <p class="mt-1 text-sm text-gray-500">Available Leave Credits</p>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-gray-900">{{ $employee?->total_vacation_leave }}</span>
                        <span class="text-gray-600">Vacation days left</span>
                    </div>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-gray-900">{{ $employee?->total_sick_leave }}</span>
                        <span class="text-gray-600">Sick days left</span>
                    </div>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-gray-900">{{ $employee?->total_personal_leave }}</span>
                        <span class="text-gray-600">Personal days left</span>
                    </div>

                </div>
                <div class="mt-5 md:col-span-2 md:mt-0">
                    <div class="grid grid-cols-6 gap-6">

                        <!-- <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">First Name</label>
                            <input wire:model.live="from" type="text" id="from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input wire:model.live="from" type="text" id="from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div> -->

                        <div class="col-span-6 sm:col-span-6">
                            <label for="country" class="block text-sm font-medium text-gray-700">Leave Type</label>
                            <select wire:model.live="leaveTypeId" id="country" name="country" autocomplete="country-name" class="mt-1 block w-full rounded-md border border-gray-300 bg-white py-2 px-3 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-primary-500 sm:text-sm">
                                @foreach ($leaveOptions as $key => $val )
                                <option value="{{ $key }}">{{ $val }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">From</label>
                            <input wire:model.live="dateFrom" type="date" id="from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('dateFrom') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="to" class="block text-sm font-medium text-gray-700">To</label>
                            <input wire:model.live="dateTo" type="date" id="to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('dateTo') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">With Pay Number of days</label>
                            <input wire:model.live="daysWithPay" readonly type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Without Pay Number of days</label>
                            <input wire:model.live="daysWithoutPay" readonly type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">File Attachment</label>
                            <input wire:model.live="attachments" type="file" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Reason</label>
                            <input wire:model.live="reason" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('reason') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <!-- <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Date</label>
                            <input wire:model.live="dateApply" type="date" id="from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div> -->

                        <div class="col-span-6 sm:col-span-3">

                            <div>
                                <label for="to" class="block text-sm font-medium text-gray-700">Options</label>

                                <fieldset class="mt-4">
                                    <legend class="sr-only">Notification method</legend>
                                    <div class="space-y-4 sm:flex sm:items-center sm:space-y-0 sm:space-x-10">
                                        <div class="flex items-center">
                                            <input id="whole" wire:model.live="isWholeDay" value="true" type="radio" checked class="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <label for="whole" class="ml-3 block text-sm font-medium text-gray-700">Whole Day</label>
                                        </div>

                                        <div class="flex items-center">
                                            <input id="half" wire:model.live="isWholeDay" value="false" type="radio" class="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500">
                                            <label for="half" class="ml-3 block text-sm font-medium text-gray-700">Half Day</label>
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
            <a href="{{ route('portal.leaves') }}" wire:navigate class="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="applyLeave" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-primary-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Apply</button>
        </div>
    </form>
</div>