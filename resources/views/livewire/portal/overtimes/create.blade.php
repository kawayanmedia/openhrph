<div class="px-4 py-5 sm:p-6">
    <div class="border-b border-gray-200 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-medium leading-6 text-gray-900">{{ 'Overtime Application' }}</h3>
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

                    <div class="grid grid-rows-3 grid-flow-col gap-3 pt-2">

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <p class="mt-1 text-sm text-gray-500">_____</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="block text-sm font-medium text-gray-700">Position</label>
                            <p class="mt-1 text-sm text-gray-500">_____</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="block text-sm font-medium text-gray-700">Department</label>
                            <p class="mt-1 text-sm text-gray-500">_____</p>
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="to" class="block text-sm font-medium text-gray-700">Date Filed</label>
                            <p class="mt-1 text-sm text-gray-500">{{ now()->toDateString() }}</p>
                        </div>

                    </div>


                </div>
                <div class="mt-5 md:col-span-2 md:mt-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Effective Date</h3>
                    <div class="grid grid-cols-6 gap-6 pt-2">
                        <div class="col-span-6 sm:col-span-6">
                            <label for="from" class="block text-sm font-medium text-gray-700">Date</label>
                            <input wire:model.live="date" type="date" id="from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('date') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">From</label>
                            <input wire:model.live="timeFrom" type="time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('timeFrom') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="to" class="block text-sm font-medium text-gray-700">To</label>
                            <input wire:model.live="timeTo" type="time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('timeTo') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="hours" class="block text-sm font-medium text-gray-700">Number of Hours</label>
                            <input readonly wire:model.live="hours" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('hours') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-6">
                            <label for="reason" class="block text-sm font-medium text-gray-700">Reason</label>
                            <input wire:model.live="reason" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('reason') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                   

                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('portal.overtimes') }}" wire:navigate class="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="store" type="submit" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-primary-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Submit</button>
        </div>
    </form>
</div>