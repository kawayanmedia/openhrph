<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ $title }}</h3>
        <div class="mt-3 sm:mt-0 sm:ml-4">
        </div>
    </div>



    <div class="-mx-4 mt-8 flex flex-col sm:-mx-6 md:mx-0">
        <table class="ui-table">
            <thead>
                <tr>
                    <!-- <th scope="col" class="ui-th text-right">Rate</th>
          <th scope="col" class="ui-th text-right">Price</th> -->
                </tr>
            </thead>
            <tbody>

            </tbody>
            <!-- <tfoot>
        <tr>
          <th scope="row" colspan="3" class="hidden pl-6 pr-3 pt-6 text-right text-sm font-normal text-slate-500 dark:text-slate-400 sm:table-cell md:pl-0">Subtotal</th>
          <th scope="row" class="pl-4 pr-3 pt-6 text-left text-sm font-normal text-slate-500 dark:text-slate-400 sm:hidden">Subtotal</th>
          <td class="pl-3 pr-4 pt-6 text-right text-sm text-slate-500 dark:text-slate-400 sm:pr-6 md:pr-0">$3,900.00</td>
        </tr>
        <tr>
          <th scope="row" colspan="3" class="hidden pl-6 pr-3 pt-4 text-right text-sm font-normal text-slate-500 dark:text-slate-400 sm:table-cell md:pl-0">Tax</th>
          <th scope="row" class="pl-4 pr-3 pt-4 text-left text-sm font-normal text-slate-500 dark:text-slate-400 sm:hidden">Tax</th>
          <td class="pl-3 pr-4 pt-4 text-right text-sm text-slate-500 dark:text-slate-400 sm:pr-6 md:pr-0">$585.00</td>
        </tr>
        <tr>
          <th scope="row" colspan="3" class="hidden pl-6 pr-3 pt-4 text-right text-sm font-semibold text-navy-900 dark:text-white sm:table-cell md:pl-0">Total</th>
          <th scope="row" class="pl-4 pr-3 pt-4 text-left text-sm font-semibold text-navy-900 dark:text-white sm:hidden">Total</th>
          <td class="pl-3 pr-4 pt-4 text-right text-sm font-semibold text-navy-900 dark:text-white sm:pr-6 md:pr-0">$4,485.00</td>
        </tr>
      </tfoot> -->
        </table>
    </div>

    <div class="mt-8 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                @if (count($rows) != 0)
                <div class="overflow-hidden shadow ring-1 ring-slate-900/5 dark:ring-white/10 md:rounded-lg">
                    <table class="ui-table">
                        <thead class="bg-slate-50 dark:bg-navy-800/40">
                            <tr>
                                <th scope="col" class="ui-th">Plantilla Item</th>
                                <th scope="col" class="ui-th">Position Title</th>
                                <th scope="col" class="ui-th">Place of Assignment</th>
                                <th scope="col" class="ui-th">Salary Grade</th>
                                <th scope="col" class="ui-th">Action</th>
                                <!-- <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                  <span class="sr-only">Edit</span>
                </th> -->
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-navy-800">
                            @foreach ($rows as $row)
                            <tr>
                                <td class="ui-td font-medium text-navy-900 dark:text-white">{{ $row->plantillaItem->item_number }}</td>
                                <td class="ui-td">{{ $row->plantillaItem->position_title }}</td>
                                <td class="ui-td">{{ $row->plantillaItem->office_name }}</td>
                                <td class="ui-td">{{ $row->salary_grade_step }}</td>
                                <td class="ui-td">
                                    <a href="{{ route('portal.job.apply', $row->id) }}" wire:navigate class="ui-btn-primary">Apply</a>
                                </td>
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