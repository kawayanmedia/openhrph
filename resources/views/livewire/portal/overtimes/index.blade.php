<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ $title }}</h3>
        <div class="mt-3 sm:mt-0 sm:ml-4">
            <a href="{{ route('portal.overtimes.create') }}" wire:navigate class="ui-btn-primary">Add</a>
        </div>
    </div>

    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:pb-0 pt-10">
        <!-- <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">My Leave Applications</h3> -->
        <x-navigation-tab :options="$options" :selectedTab="$selectedTab" clickCallback="selectTab" />
    </div>

    <div class="mt-8 flex flex-col">
    <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
      @if (count($rows) != 0)
        <div class="overflow-hidden shadow ring-1 ring-slate-900/5 dark:ring-white/10 md:rounded-lg">
          <table class="ui-table">
            <thead class="bg-slate-50 dark:bg-navy-800/40">
              <tr>
                <th scope="col" class="ui-th">Status</th>
                <th scope="col" class="ui-th">Date Filed</th>
                <th scope="col" class="ui-th">Type</th>
                <th scope="col" class="ui-th">Reason</th>
                <th scope="col" class="ui-th">Remarks</th>
                <th scope="col" class="ui-th">Approved By</th>
                <th scope="col" class="ui-th">Date Approved</th>

                <!-- <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                  <span class="sr-only">Edit</span>
                </th> -->
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-navy-800">
            @foreach ($rows as $row)
              <tr>
                <td class="ui-td font-medium text-navy-900 dark:text-white">{{ $row->status }}</td>
                <td class="ui-td">{{ $row->date_filed->format('M d, Y') }}</td>
                <td class="ui-td">{{ $row->type }}</td>
                <td class="ui-td">{{ $row->reason }}</td>
                <td class="ui-td">{{ $row->remarks }}</td>
                <td class="ui-td">{{ $row->employeeApproved?->fullname }}</td>
                <td class="ui-td">{{ $row->approved_at?->format('M d, Y') }}</td>
                <!-- <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                  <a href="#" class="text-teal-700 dark:text-teal-300 hover:text-teal-800 dark:hover:text-teal-200">Edit<span class="sr-only">, Lindsay Walton</span></a>
                </td> -->
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
            <div class="text-slate-500 dark:text-slate-400">Nothing here...</div>
            @endif
      </div>
    </div>
  </div>

</div>