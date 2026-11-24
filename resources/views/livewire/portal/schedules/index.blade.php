<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ $title }}</h3>
        <div class="mt-3 sm:mt-0 sm:ml-4">
            <!-- <a href="{{ route('portal.schedules.create') }}" class="ui-btn-primary">Add</a> -->
        </div>
    </div>

    {{-- 
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:pb-0 pt-10">
        <!-- <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">My Leave Applications</h3> -->
        
        <x-navigation-tab :options="$options" :selectedTab="$selectedTab" clickCallback="selectTab" />
       
    </div> --}}

    <div class="mt-8 flex flex-col">
    <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
        <div class="overflow-hidden shadow ring-1 ring-slate-900/5 dark:ring-white/10 md:rounded-lg">
          <table class="ui-table">
            <thead class="bg-slate-50 dark:bg-navy-800/40">
              <tr>
                <th scope="col" class="ui-th uppercase">Day</th>
                <th scope="col" class="ui-th uppercase">shift/core from</th>
                <th scope="col" class="ui-th uppercase">shift/core to</th>
                <th scope="col" class="ui-th uppercase">break start</th>
                <th scope="col" class="ui-th uppercase">break end</th>
                <th scope="col" class="ui-th uppercase">Is rest day</th>

                <!-- <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                  <span class="sr-only">Edit</span>
                </th> -->
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-navy-800">
              <tr>
                <td class="ui-td font-medium text-navy-900 dark:text-white">Sunday</td>
                <td class="ui-td"></td>
                <td class="ui-td"></td>
                <td class="ui-td"></td>
                <td class="ui-td"></td>
                <td class="ui-td"></td>
              </tr>
              <tr>
                <td class="ui-td font-medium text-navy-900 dark:text-white">Monday</td>
                <td class="ui-td">08:00 AM</td>
                <td class="ui-td">05:00 PM</td>
                <td class="ui-td">12:00 PM</td>
                <td class="ui-td">01:00 PM</td>
                <td class="ui-td"><input type="checkbox" class="rounded-md border-slate-300 dark:border-navy-700 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm"/></td>
              </tr>
              <tr>
                <td class="ui-td font-medium text-navy-900 dark:text-white">Tuesday</td>
                <td class="ui-td">08:00 AM</td>
                <td class="ui-td">05:00 PM</td>
                <td class="ui-td">12:00 PM</td>
                <td class="ui-td">01:00 PM</td>
                <td class="ui-td"><input type="checkbox" class="rounded-md border-slate-300 dark:border-navy-700 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm"/></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>