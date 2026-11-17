<div class="px-4 py-5 sm:p-6">
    <div class="border-b border-gray-200 pb-5 sm:flex sm:items-center sm:justify-between">
        <h3 class="text-lg font-medium leading-6 text-gray-900">{{ $title }}</h3>
        <div class="mt-3 sm:mt-0 sm:ml-4">
            <!-- <a href="{{ route('portal.schedules.create') }}" class="inline-flex items-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Add</a> -->
        </div>
    </div>

    {{-- 
    <div class="border-b border-gray-200 pb-5 sm:pb-0 pt-10">
        <!-- <h3 class="text-lg font-medium leading-6 text-gray-900">My Leave Applications</h3> -->
        
        <x-navigation-tab :options="$options" :selectedTab="$selectedTab" clickCallback="selectTab" />
       
    </div> --}}

    <div class="mt-8 flex flex-col">
    <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
          <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6 uppercase">Day</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 uppercase">shift/core from</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 uppercase">shift/core to</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 uppercase">break start</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 uppercase">break end</th>
                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 uppercase">Is rest day</th>

                <!-- <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                  <span class="sr-only">Edit</span>
                </th> -->
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr>
                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">Sunday</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"></td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"></td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"></td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"></td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"></td>
              </tr>
              <tr>
                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">Monday</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">08:00 AM</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">05:00 PM</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">12:00 PM</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">01:00 PM</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"><input type="checkbox" class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"/></td>
              </tr>
              <tr>
                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">Tuesday</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">08:00 AM</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">05:00 PM</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">12:00 PM</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">01:00 PM</td>
                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"><input type="checkbox" class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"/></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>