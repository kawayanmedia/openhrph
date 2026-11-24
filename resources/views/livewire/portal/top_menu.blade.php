<div class="relative inline-block text-left pt-1">
    <div>
        <!-- <button @click="tools = !tools" type="button" class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-white dark:bg-navy-900 px-3 py-2 text-sm font-semibold text-navy-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-slate-50 dark:bg-navy-800/40" id="menu-button" aria-expanded="true" aria-haspopup="true">
                                    Options
                                    <svg class="-mr-1 h-5 w-5 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button> -->
        <a href="#" @click.prevent="requests = !requests" class="{{ portalMainActiveRoute('requests', '', '') }}">My Requests</a>
    </div>

    <!--
    Dropdown menu, show/hide based on menu state.

    Entering: "transition ease-out duration-100"
      From: "transform opacity-0 scale-95"
      To: "transform opacity-100 scale-100"
    Leaving: "transition ease-in duration-75"
      From: "transform opacity-100 scale-100"
      To: "transform opacity-0 scale-95"
  -->
    <div x-show="requests" @click.away="requests = false" class="absolute right-0 z-10 mt-2 w-56 origin-top-right divide-y divide-slate-100 dark:divide-navy-800 rounded-md bg-white dark:bg-navy-900 shadow-lg ring-1 ring-slate-900/5 dark:ring-white/10 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1">
        <div class="py-1" role="none">
            <!-- Active: "bg-slate-100 dark:bg-navy-800 text-navy-900 dark:text-white", Not Active: "text-slate-700 dark:text-slate-200" -->
            <a href="{{ route('portal.leaves') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:bg-navy-800 focus:outline-none focus:bg-slate-100 dark:bg-navy-800 transition duration-150 ease-in-out" role="menuitem" tabindex="-1" id="menu-item-0">My Leaves</a>
            <a href="{{ route('portal.attendance') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:bg-navy-800 focus:outline-none focus:bg-slate-100 dark:bg-navy-800 transition duration-150 ease-in-out" role="menuitem" tabindex="-1" id="menu-item-1">My Certificate of Attendance</a>
            <a href="{{ route('portal.timesheet') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:bg-navy-800 focus:outline-none focus:bg-slate-100 dark:bg-navy-800 transition duration-150 ease-in-out" role="menuitem" tabindex="-1" id="menu-item-timesheet">My Timesheet</a>
            <a href="{{ route('portal.schedules') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:bg-navy-800 focus:outline-none focus:bg-slate-100 dark:bg-navy-800 transition duration-150 ease-in-out" role="menuitem" tabindex="-1" id="menu-item-2">My Schedules</a>
            <a href="{{ route('portal.schedulesadjustment') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:bg-navy-800 focus:outline-none focus:bg-slate-100 dark:bg-navy-800 transition duration-150 ease-in-out" role="menuitem" tabindex="-1" id="menu-item-2">My Schedule Adjustments</a>
            <a href="{{ route('portal.overtimes') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:bg-navy-800 focus:outline-none focus:bg-slate-100 dark:bg-navy-800 transition duration-150 ease-in-out" role="menuitem" tabindex="-1" id="menu-item-3">My Overtimes</a>
            <a href="{{ route('portal.undertimes') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:bg-navy-800 focus:outline-none focus:bg-slate-100 dark:bg-navy-800 transition duration-150 ease-in-out" role="menuitem" tabindex="-1" id="menu-item-4">My Undertimes</a>
            <a href="{{ route('portal.officialbusiness') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:bg-navy-800 focus:outline-none focus:bg-slate-100 dark:bg-navy-800 transition duration-150 ease-in-out" role="menuitem" tabindex="-1" id="menu-item-5">My Official Business</a>
        </div>
        <!-- <div class="py-1" role="none">
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-2">Archive</a>
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-3">Move</a>
        </div>
        <div class="py-1" role="none">
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-4">Share</a>
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-5">Add to favorites</a>
        </div>
        <div class="py-1" role="none">
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-6">Delete</a>
        </div> -->
    </div>
</div>



<div class="relative inline-block text-left pt-1">
    <div>
        <!-- <button @click="tools = !tools" type="button" class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-white dark:bg-navy-900 px-3 py-2 text-sm font-semibold text-navy-900 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-slate-50 dark:bg-navy-800/40" id="menu-button" aria-expanded="true" aria-haspopup="true">
                                    Options
                                    <svg class="-mr-1 h-5 w-5 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button> -->
        <a href="#" @click.prevent="tools = !tools" class="rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white">Administration Tools</a>
    </div>

    <!--
    Dropdown menu, show/hide based on menu state.

    Entering: "transition ease-out duration-100"
      From: "transform opacity-0 scale-95"
      To: "transform opacity-100 scale-100"
    Leaving: "transition ease-in duration-75"
      From: "transform opacity-100 scale-100"
      To: "transform opacity-0 scale-95"
  -->
    <div x-show="tools" @click.away="tools = false" class="absolute right-0 z-10 mt-2 w-56 origin-top-right divide-y divide-slate-100 dark:divide-navy-800 rounded-md bg-white dark:bg-navy-900 shadow-lg ring-1 ring-slate-900/5 dark:ring-white/10 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1">
        <div class="py-1" role="none">
            <!-- Active: "bg-slate-100 dark:bg-navy-800 text-navy-900 dark:text-white", Not Active: "text-slate-700 dark:text-slate-200" -->
            <a href="{{ route('portal.atendance.report') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:bg-navy-800 focus:outline-none focus:bg-slate-100 dark:bg-navy-800 transition duration-150 ease-in-out" role="menuitem" tabindex="-1" id="menu-item-0">Attendance Report</a>
        </div>
        <!-- <div class="py-1" role="none">
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-2">Archive</a>
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-3">Move</a>
        </div>
        <div class="py-1" role="none">
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-4">Share</a>
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-5">Add to favorites</a>
        </div>
        <div class="py-1" role="none">
            <a href="#" class="text-slate-700 dark:text-slate-200 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="menu-item-6">Delete</a>
        </div> -->
    </div>
</div>