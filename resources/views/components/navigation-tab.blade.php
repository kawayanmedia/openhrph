<div>
    <div class="mt-3 sm:mt-4">
        <!-- Dropdown menu on small screens -->
        <div class="sm:hidden">
            <label for="current-tab" class="sr-only">Select a tab</label>
            <select wire:model.live="selectedTabDropdown" id="current-tab" name="current-tab" class="block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-primary-500 focus:outline-none focus:ring-primary-500 sm:text-sm">
                @foreach ($options as $key => $option)
                <option value="{{ $key }}">{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <!-- Tabs at small breakpoint and up -->
        <div class="hidden sm:block">
            <nav class="-mb-px flex space-x-8">
                <!-- Current: "border-primary-500 text-primary-600", Default: "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300" -->
                @foreach ($options as $key => $option)
                <a wire:click.prevent="{{ $clickCallback }}('{{ $key }}')" href="" class="{{ selectedTab($key, $selectedTab) }}">{{ $option }}</a>
                @endforeach
            </nav>
        </div>
    </div>
</div>