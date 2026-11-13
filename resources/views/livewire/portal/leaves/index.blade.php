<div class="px-4 py-5 sm:p-6">
    <div class="border-b border-gray-200 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-medium leading-6 text-gray-900">{{ $title }}</h3>
        <div class="mt-3 sm:mt-0 sm:ml-4">
            <a href="{{ route('portal.leaves.create') }}" wire:navigate class="inline-flex items-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Add</a>
        </div>
    </div>



    <div class="-mx-4 mt-8 flex flex-col sm:-mx-6 md:mx-0">
        <table class="min-w-full divide-y divide-gray-300">
            <thead>
                <tr>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6 md:pl-0">Leave Type</th>
                    <th scope="col" class="hidden py-3.5 px-3 text-right text-sm font-semibold text-gray-900 sm:table-cell">Credits</th>
                    <!-- <th scope="col" class="hidden py-3.5 px-3 text-right text-sm font-semibold text-gray-900 sm:table-cell">Rate</th>
          <th scope="col" class="py-3.5 pl-3 pr-4 text-right text-sm font-semibold text-gray-900 sm:pr-6 md:pr-0">Price</th> -->
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-200">
                    <td class="py-4 pl-4 pr-3 text-sm sm:pl-6 md:pl-0">
                        <div class="font-medium text-gray-900">Vacation Leave</div>
                        <!-- <div class="mt-0.5 text-gray-500 sm:hidden">12.0 hours at $75.00</div> -->
                    </td>
                    <td class="hidden py-4 px-3 text-right text-sm text-gray-500 sm:table-cell">{{ $employee?->total_vacation_leave }}</td>
                    <!-- <td class="hidden py-4 px-3 text-right text-sm text-gray-500 sm:table-cell">$75.00</td>
          <td class="py-4 pl-3 pr-4 text-right text-sm text-gray-500 sm:pr-6 md:pr-0">$900.00</td> -->
                </tr>
                <tr class="border-b border-gray-200">
                    <td class="py-4 pl-4 pr-3 text-sm sm:pl-6 md:pl-0">
                        <div class="font-medium text-gray-900">Sick Leave</div>
                        <!-- <div class="mt-0.5 text-gray-500 sm:hidden">12.0 hours at $75.00</div> -->
                    </td>
                    <td class="hidden py-4 px-3 text-right text-sm text-gray-500 sm:table-cell">{{ $employee?->total_sick_leave }}</td>
                    <!-- <td class="hidden py-4 px-3 text-right text-sm text-gray-500 sm:table-cell">$75.00</td>
          <td class="py-4 pl-3 pr-4 text-right text-sm text-gray-500 sm:pr-6 md:pr-0">$900.00</td> -->
                </tr>

            </tbody>
            <!-- <tfoot>
        <tr>
          <th scope="row" colspan="3" class="hidden pl-6 pr-3 pt-6 text-right text-sm font-normal text-gray-500 sm:table-cell md:pl-0">Subtotal</th>
          <th scope="row" class="pl-4 pr-3 pt-6 text-left text-sm font-normal text-gray-500 sm:hidden">Subtotal</th>
          <td class="pl-3 pr-4 pt-6 text-right text-sm text-gray-500 sm:pr-6 md:pr-0">$3,900.00</td>
        </tr>
        <tr>
          <th scope="row" colspan="3" class="hidden pl-6 pr-3 pt-4 text-right text-sm font-normal text-gray-500 sm:table-cell md:pl-0">Tax</th>
          <th scope="row" class="pl-4 pr-3 pt-4 text-left text-sm font-normal text-gray-500 sm:hidden">Tax</th>
          <td class="pl-3 pr-4 pt-4 text-right text-sm text-gray-500 sm:pr-6 md:pr-0">$585.00</td>
        </tr>
        <tr>
          <th scope="row" colspan="3" class="hidden pl-6 pr-3 pt-4 text-right text-sm font-semibold text-gray-900 sm:table-cell md:pl-0">Total</th>
          <th scope="row" class="pl-4 pr-3 pt-4 text-left text-sm font-semibold text-gray-900 sm:hidden">Total</th>
          <td class="pl-3 pr-4 pt-4 text-right text-sm font-semibold text-gray-900 sm:pr-6 md:pr-0">$4,485.00</td>
        </tr>
      </tfoot> -->
        </table>
    </div>



    <div class="border-b border-gray-200 pb-5 sm:pb-0 pt-10">
        <h3 class="text-lg font-medium leading-6 text-gray-900">My Leave Applications</h3>
        <x-navigation-tab :options="$options" :selectedTab="$selectedTab" clickCallback="selectTab" />
    </div>

    <div class="mt-8 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
            @if (count($rows) != 0)
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Status</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date Filed</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Leave Type</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date From</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date To</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">With Pay # of Days</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Without Pay # of Days</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Reason</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Reject Reason</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date Rejected Cancelled</th>

                                <!-- <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                  <span class="sr-only">Edit</span>
                </th> -->
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ $row->status }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->date_filed->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->leave->name }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->date_from->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->date_to->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->days_with_pay }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->days_without_pay }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->reason }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->reject_reason }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $row->rejected_at?->format('M d, Y') }}</td>
                                <!-- <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                  <a href="#" class="text-primary-600 hover:text-primary-900">Edit<span class="sr-only">, Lindsay Walton</span></a>
                </td> -->
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
            <div class="text-gray-500">Nothing here...</div>
            @endif
            </div>
        </div>
    </div>
</div>