<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Group;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Creates the first company owner for a fresh installation or a new tenant.
 *
 * The command is intentionally independent of HTTP/tenant context: owners are
 * global users and companies are unscoped, so it can run from any deployment
 * shell (SSM Session Manager, a container task, or a local terminal).
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
        {--name= : Full name}
        {--email= : Email address}
        {--password= : Password (prompted when omitted)}
        {--company= : Existing company id}
        {--company-name= : Company name when creating a new company}
        {--force : Update the existing user with this email}';

    protected $description = 'Create a company owner account for the admin panel';

    public function handle(): int
    {
        try {
            $name = (string) ($this->option('name') ?: $this->askForValue('Full name'));
            $email = (string) ($this->option('email') ?: $this->askForValue('Email address'));
            $password = (string) ($this->option('password') ?: $this->askForPassword());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:50'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && ! $this->option('force')) {
            $this->error("A user with {$email} already exists. Re-run with --force to update it.");

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($name, $email, $password, $existing): array {
                $user = $existing ?? new User;
                $parts = preg_split('/\s+/', trim($name), 2) ?: [];
                $firstName = $parts[0] ?? $name;
                $lastName = $parts[1] ?? '';

                $user->forceFill([
                    'company_id' => $existing?->company_id,
                    'name' => Str::substr($name, 0, 32),
                    'first_name' => Str::substr($firstName, 0, 25),
                    'last_name' => Str::substr($lastName, 0, 25),
                    'email' => $email,
                    'password' => Hash::make($password),
                    'owner' => 1,
                    'is_temporary_password' => 0,
                ]);

                if (! $user->exists) {
                    $user->account_id = 0;
                }

                $user->save();

                $company = $this->resolveCompany($user, $existing);

                $user->forceFill(['company_id' => $company->getKey()])->save();

                $group = Group::query()
                    ->withoutTenant()
                    ->where('company_id', $company->getKey())
                    ->orderBy('id')
                    ->first();

                $user->forceFill(['group_id' => $group?->getKey()])->save();

                DB::table('company_user')->updateOrInsert(
                    [
                        'company_id' => $company->getKey(),
                        'user_id' => $user->getKey(),
                    ],
                    [
                        'is_owner' => 1,
                        'group_id' => $group?->getKey(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                return [
                    'user' => $user->fresh(),
                    'company' => $company->fresh(),
                ];
            });
        } catch (Throwable $e) {
            $this->error('Could not create the administrator: '.$e->getMessage());

            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }

        /** @var User $user */
        $user = $result['user'];
        /** @var Company $company */
        $company = $result['company'];

        $this->newLine();
        $this->info('Administrator ready.');
        $this->line('  Email:   '.$user->email);
        $this->line('  Company: '.$company->name.' (id '.$company->getKey().')');
        $this->line('  Sign in: '.url(config('admin.path').'/login'));

        return self::SUCCESS;
    }

    private function askForValue(string $label): string
    {
        if (! $this->input->isInteractive()) {
            throw new RuntimeException("{$label} is required. Pass the matching option.");
        }

        return (string) $this->ask($label);
    }

    private function askForPassword(): string
    {
        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Password is required. Pass --password.');
        }

        return (string) $this->secret('Password (minimum 8 characters)');
    }

    private function defaultCompanyName(): string
    {
        return (string) config('app.name', 'HRIS');
    }

    /**
     * Resolve the company to attach the owner to.
     */
    private function resolveCompany(User $user, ?User $existing): Company
    {
        $companyId = $this->option('company');

        if ($companyId !== null && $companyId !== '') {
            $company = Company::query()->find($companyId);

            if ($company === null) {
                throw new RuntimeException("Company [{$companyId}] was not found.");
            }

            return $company;
        }

        if ($existing?->company_id !== null) {
            $existingCompany = Company::query()->find($existing->company_id);

            if ($existingCompany !== null) {
                return $existingCompany;
            }
        }

        $companyName = $this->option('company-name');

        if ($companyName !== null && $companyName !== '') {
            $company = Company::query()->where('name', Str::substr($companyName, 0, 64))->first();

            if ($company !== null) {
                return $company;
            }

            return $this->createCompany($user, (string) $companyName);
        }

        $companies = Company::query()->orderBy('name')->get();

        if ($companies->count() === 1) {
            return $companies->first();
        }

        if ($companies->count() > 1) {
            if (! $this->input->isInteractive()) {
                throw new RuntimeException('Multiple companies exist. Pass --company=<id>.');
            }

            $choice = $this->choice(
                'Which company should this owner belong to?',
                $companies->pluck('name', 'id')->all(),
            );

            return Company::query()->findOrFail($choice);
        }

        $name = $this->input->isInteractive()
            ? (string) $this->ask('Company name', $this->defaultCompanyName())
            : $this->defaultCompanyName();

        return $this->createCompany($user, $name);
    }

    private function createCompany(User $user, string $name): Company
    {
        $company = new Company;
        $company->forceFill([
            'user_id' => $user->getKey(),
            'name' => Str::substr($name, 0, 64),
            'type' => 'Private',
            'email' => $this->option('email') ?: $user->email,
        ])->save();

        return $company;
    }
}
