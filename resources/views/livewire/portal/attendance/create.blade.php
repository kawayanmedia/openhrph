<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ $title }}</h3>
    </div>
    <form class="space-y-6" wire:submit="submit">

        @if (! empty($applications))

        <div class="col-span-6 sm:col-span-6">
            <div class="flex flex-col">
                <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                        <div class="shadow overflow-hidden border-b border-slate-200 dark:border-navy-800 sm:rounded-lg">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-navy-800">
                                <thead class="bg-slate-50 dark:bg-navy-800/40">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Reason
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            time
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            log type
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($applications as $key => $orderItem)
                                    <tr class="bg-white dark:bg-navy-900">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-navy-900 dark:text-white">
                                            <select wire:model.live="applications.{{ $key }}.type" class="ui-select mt-1">
                                            <option value="Biometric Malfunction">Biometric Malfunction</option>
                                            </select>

                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                            <input type="text" wire:model.live="applications.{{ $key }}.reason" class="ui-input mt-1" />                             
                                            @error('applications.{{ $key }}.reason') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                            <input wire:model.live="applications.{{ $key }}.date" type="date" class="mt-1 block w-30 rounded-md border-slate-300 dark:border-navy-700 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400 text-right">
                                            <input wire:model.live="applications.{{ $key }}.time" type="time" class="mt-1 block w-30 rounded-md border-slate-300 dark:border-navy-700 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm">
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-navy-900 dark:text-white">
                                            <select wire:model.live="applications.{{ $key }}.log_type" class="ui-select mt-1">
                                                <option value="0">IN</option>
                                                <option value="1">OUT</option>
                                            </select>

                                        </td>

                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        @endif

        <div class="flex justify-end">
            <button wire:click.prevent="addApplication" type="button" class="inline-flex items-center py-2 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:bg-navy-800/40 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                <!-- Heroicon name: mini/envelope -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add
            </button>
        </div>


        <div class="flex justify-end">
            <a href="{{ route('portal.attendance') }}" wire:navigate class="rounded-md border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-900 py-2 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 shadow-sm hover:bg-slate-50 dark:bg-navy-800/40 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="store" type="submit" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-teal-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Submit</button>
        </div>
    </form>
</div>