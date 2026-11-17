<div class="px-4 py-5 sm:p-6">
    <div class="border-b border-gray-200 pb-5 sm:flex sm:items-center sm:justify-between">
        <a href="{{ route('portal.jobs') }}" wire:navigate class="inline-flex items-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">back</a>
        <h3 class="text-lg font-medium leading-6 text-gray-900">Job Application</h3>
    </div>
    <form class="space-y-6" wire:submit="submit">

        <div class="bg-white px-4 py-5 shadow sm:rounded-lg sm:p-6">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">{{ $jobVacancy->plantillaItem->position_title }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $jobVacancy->plantillaItem->office_name }}</p>
                    <p class="mt-1 text-sm text-gray-500">Salary Grade: {{ $jobVacancy->salary_grade_step }}</p>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-gray-900"></span>
                        <!-- <span class="text-gray-600">Vacation days left</span> -->
                    </div>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-gray-900"></span>
                        <!-- <span class="text-gray-600">Sick days left</span> -->
                    </div>

                </div>
                <div class="mt-5 md:col-span-2 md:mt-0">
                    <div class="grid grid-cols-6 gap-6">

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Name</label>
                            <input wire:model.live="name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('name') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Gender</label>
                            <select wire:model.live="gender" id="gender" name="gender" class="mt-1 block w-full rounded-md border border-gray-300 bg-white py-2 px-3 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-primary-500 sm:text-sm">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                            @error('gender') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Address</label>
                            <input wire:model.live="address" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('address') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="to" class="block text-sm font-medium text-gray-700">Birth date</label>
                            <input wire:model.live="birth_date" type="date" id="to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('birth_date') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>



                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Educational attainment</label>
                            <input wire:model.live="educational_attainment" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('educational_attainment') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Telephone Number</label>
                            <input wire:model.live="telephone" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('telephone') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Email</label>
                            <input wire:model.live="email" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('email') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Eligibility</label>
                            <input wire:model.live="eligibility" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('eligibility') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Training</label>
                            <input wire:model.live="training" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('training') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Experience</label>
                            <input wire:model.live="experience" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            @error('experience') <span class="mt-2 text-red-600">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Training File Attachment</label>
                            <input wire:model.live="training_file" type="file" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="block text-sm font-medium text-gray-700">Experience File Attachment</label>
                            <input wire:model.live="experience_file" type="file" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('portal.jobs') }}" wire:navigate class="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="applyLeave" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-primary-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Apply</button>
        </div>
    </form>
</div>