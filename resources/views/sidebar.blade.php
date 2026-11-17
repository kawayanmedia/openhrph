<div x-data="{ sideOpen: false }" class="space-y-1">
<!-- Current: "bg-gray-100 text-gray-900", Default: "bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900" -->
<button @click="sideOpen = !sideOpen" type="button" class="{{ isActiveRouteSideBar('employees.create', '') }} hover:bg-gray-50 hover:text-gray-900 group w-full flex items-center pl-2 pr-1 py-2 text-left text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-controls="sub-menu-1" aria-expanded="false">
    <!-- Heroicon name: outline/users -->
    <svg class="mr-3 h-6 w-6 flex-shrink-0 text-gray-400 group-hover:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
    </svg>
    <span class="flex-1">Settings</span>
    <!-- Expanded: "text-gray-400 rotate-90", Collapsed: "text-gray-300" -->
    <svg class="text-gray-300 ml-3 h-5 w-5 flex-shrink-0 transform transition-colors duration-150 ease-in-out group-hover:text-gray-400" viewBox="0 0 20 20" aria-hidden="true">
    <path d="M6 6L14 10L6 14V6Z" fill="currentColor" />
    </svg>
</button>
<!-- Expandable link section, show/hide based on state. -->
<div x-show="sideOpen" class="space-y-1" id="sub-menu-1">
    <!-- <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Company Settings</a>
    <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Locations</a>
    <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Departments</a> -->
    <!-- <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Employee Types</a>
    <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Employees</a>
    <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Users</a>
    <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Devices</a> -->
    
    <div class="space-y-1">
        <h3 class="px-3 text-sm font-medium text-gray-500" id="projects-headline">COMPANY</h3>
        <div class="space-y-1" role="group" aria-labelledby="projects-headline">
        <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Company Settings</a>
        <a href="{{ route('locations.index') }}" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Locations</a>
        <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Departments</a>
        <!-- <a href="#" class="group flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <span class="truncate">Website redesign</span>
        </a>

        <a href="#" class="group flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <span class="truncate">GraphQL API</span>
        </a> -->
        </div>
    </div>

    <div class="space-y-1">
        <h3 class="px-3 text-sm font-medium text-gray-500" id="projects-headline">EMPLOYEE</h3>
        <div class="space-y-1" role="group" aria-labelledby="projects-headline">
        <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Employee Types</a>
    <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Employees</a>
    <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Users</a>
    <a href="#" class="group flex w-full items-center rounded-md py-2 pl-11 pr-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">Devices</a>
        <!-- <a href="#" class="group flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <span class="truncate">Website redesign</span>
        </a> -->
        </div>
    </div>

</div>
</div>