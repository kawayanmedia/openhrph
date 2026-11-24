<div class="ui-card p-5 sm:p-6">
    <div class="border-b border-slate-200 dark:border-navy-800 pb-5 sm:flex sm:items-center sm:justify-between">
        <a href="{{ route('portal.jobs') }}" wire:navigate class="ui-btn-primary">back</a>
        <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">Job Application</h3>
    </div>
    <form class="space-y-6" wire:submit="submit">

        <div class="ui-card p-5 sm:p-6">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <h3 class="text-lg font-semibold leading-6 text-navy-900 dark:text-white">{{ $jobVacancy->plantillaItem->position_title }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $jobVacancy->plantillaItem->office_name }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Salary Grade: {{ $jobVacancy->salary_grade_step }}</p>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-navy-900 dark:text-white"></span>
                        <!-- <span class="text-slate-600 dark:text-slate-300">Vacation days left</span> -->
                    </div>

                    <div class="px-6 py-5 text-center text-sm font-medium">
                        <span class="text-navy-900 dark:text-white"></span>
                        <!-- <span class="text-slate-600 dark:text-slate-300">Sick days left</span> -->
                    </div>

                </div>
                <div class="mt-5 md:col-span-2 md:mt-0">
                    <div class="grid grid-cols-6 gap-6">

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Name</label>
                            <input wire:model.live="name" type="text" class="ui-input mt-1">
                            @error('name') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Gender</label>
                            <select wire:model.live="gender" id="gender" name="gender" class="ui-select mt-1">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                            @error('gender') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Address</label>
                            <input wire:model.live="address" type="text" class="ui-input mt-1">
                            @error('address') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="to" class="ui-label">Birth date</label>
                            <input wire:model.live="birth_date" type="date" id="to" class="ui-input mt-1">
                            @error('birth_date') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>



                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Educational attainment</label>
                            <input wire:model.live="educational_attainment" type="text" class="ui-input mt-1">
                            @error('educational_attainment') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Telephone Number</label>
                            <input wire:model.live="telephone" type="text" class="ui-input mt-1">
                            @error('telephone') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Email</label>
                            <input wire:model.live="email" type="email" class="ui-input mt-1">
                            @error('email') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Eligibility</label>
                            <input wire:model.live="eligibility" type="text" class="ui-input mt-1">
                            @error('eligibility') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Training</label>
                            <input wire:model.live="training" type="text" class="ui-input mt-1">
                            @error('training') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Experience</label>
                            <input wire:model.live="experience" type="text" class="ui-input mt-1">
                            @error('experience') <span class="mt-2 text-rose-600 dark:text-rose-400">{{ $message }}</span> @enderror
                        </div>


                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Training File Attachment</label>
                            <input wire:model.live="training_file" type="file" class="ui-input mt-1">
                        </div>

                        <div class="col-span-6 sm:col-span-3">
                            <label for="from" class="ui-label">Experience File Attachment</label>
                            <input wire:model.live="experience_file" type="file" class="ui-input mt-1">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <a href="{{ route('portal.jobs') }}" wire:navigate class="rounded-md border border-slate-300 dark:border-navy-700 bg-white dark:bg-navy-900 py-2 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 shadow-sm hover:bg-slate-50 dark:bg-navy-800/40 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Cancel</a>
            <button wire:click.prevent="applyLeave" class="ml-3 inline-flex justify-center rounded-md border border-transparent bg-teal-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">Apply</button>
        </div>
    </form>
</div>