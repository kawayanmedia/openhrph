<div class="px-4 py-5 sm:p-6">
    <div class="border-b border-gray-200 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-medium leading-6 text-gray-900">{{ 'Schedule Adjustment Application' }}</h3>
        <!-- <div class="mt-3 sm:mt-0 sm:ml-4">
    <button type="button" class="inline-flex items-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Add</button>
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

        <div class="bg-white px-4 py-5 shadow sm:rounded-lg sm:p-6">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Work Schedule</h3>
                    <!-- <p class="mt-1 text-sm text-gray-500">Payroll Type</p>

                    <p class="mt-1 text-sm text-gray-500">Schedule Type</p>

                    <p class="mt-1 text-sm text-gray-500">Total Expected Hours</p> -->

                    <div class="grid grid-rows-3 grid-flow-col gap-3 pt-2">

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="block text-sm font-medium text-gray-700">Payroll Type</label>
                            <p class="mt-1 text-sm text-gray-500">Semi-Montly</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="block text-sm font-medium text-gray-700">Schedule Type</label>
                            <p class="mt-1 text-sm text-gray-500">Normal Shift</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="block text-sm font-medium text-gray-700">Total Expected Hours</label>
                            <p class="mt-1 text-sm text-gray-500">9</p>
                        </div>

                    </div>


                </div>
                <div class="mt-5 md:col-span-2 md:mt-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Effective Date</h3>
                    <div class="grid grid-cols-6 gap-6 pt-2">
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

                        <div class="col-span-6 sm:col-span-6">
                            <label for="country" class="block text-sm font-medium text-gray-700">Schedule</label>
                            <select wire:model.live="scheduleId" id="schedule_id" name="schedule_id" autocomplete="country-name" class="mt-1 block w-full rounded-md border border-gray-300 bg-white py-2 px-3 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-primary-500 sm:text-sm">
                                @foreach ($schedulesOptions as $key => $val )
                                <option value="{{ $key }}">{{ $val }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="shiftStart" class="block text-sm font-medium text-gray-700">Shift Start</label>
                            <input wire:model.live="shiftStart" type="time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('shiftStart') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror

                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="breakStart" class="block text-sm font-medium text-gray-700">Break Start</label>
                            <input wire:model.live="breakStart" type="time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('breakStart') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror

                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="breakEnd" class="block text-sm font-medium text-gray-700">Break End</label>
                            <input wire:model.live="breakEnd" type="time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('breakEnd') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror

                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="shiftEnd" class="block text-sm font-medium text-gray-700">Shift End</label>
                            <input wire:model.live="shiftEnd" type="time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('shiftEnd') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror

                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('portal.schedulesadjustment') }}" wire:navigate class="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="store" type="submit" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-primary-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Proceed</button>
        </div>
    </form>
</div>