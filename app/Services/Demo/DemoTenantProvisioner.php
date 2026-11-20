<?php

namespace App\Services\Demo;

use App\Models\Company;
use App\Models\Group;
use App\Models\User;
use App\Sectors\Catalogues\SectorCatalogue;
use App\Support\Installation\Installation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a disposable demo company for a visitor who signed up.
 *
 * Every demo account owns its own tenant, so visitors can edit or delete the
 * sample records without affecting anyone else.
 */
class DemoTenantProvisioner
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly DemoWorkspaceSeeder $workspace,
    ) {}

    /**
     * Create the owner account and their seeded demo company.
     *
     * @param  array{name: string, email: string, company_name: string, password: string}  $attributes
     */
    public function provision(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            [$firstName, $lastName] = $this->splitName($attributes['name']);

            $user = new User;
            $user->forceFill([
                'account_id' => 0,
                'name' => Str::substr($attributes['name'], 0, 32),
                'first_name' => Str::substr($firstName, 0, 25),
                'last_name' => Str::substr($lastName, 0, 25),
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'owner' => 1,
                'is_temporary_password' => 0,
            ])->save();

            $company = new Company;
            $company->forceFill([
                'user_id' => $user->getKey(),
                'name' => Str::substr($attributes['company_name'], 0, 64),
                'code' => 'DEMO-'.Str::upper(Str::random(6)),
                'type' => Installation::sector() ?? Installation::SECTOR_PRIVATE,
                'email' => $attributes['email'],
                'setup_done' => true,
            ])->save();

            $group = new Group;
            $group->forceFill([
                'company_id' => $company->getKey(),
                'name' => 'Demo Administrator',
                'permissions' => $this->adminPermissions(),
            ])->save();

            $user->forceFill([
                'company_id' => $company->getKey(),
                'group_id' => $group->getKey(),
            ])->save();

            DB::table('company_user')->updateOrInsert(
                [
                    'company_id' => $company->getKey(),
                    'user_id' => $user->getKey(),
                ],
                [
                    'is_owner' => 1,
                    'group_id' => $group->getKey(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $this->tenants->run($company, function () use ($company): void {
                SectorCatalogue::seedForCompany((int) $company->getKey());
            });

            $this->workspace->seed($company);

            return $user->fresh();
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }

    /**
     * Permissions the demo owner needs to reach every admin resource.
     *
     * @return array<string, bool>
     */
    private function adminPermissions(): array
    {
        return [
            'Company Settings' => true,
            'Offices' => true,
            'Employees' => true,
            'Users' => true,
            'Departments' => true,
            'Devices' => true,
            'Holidays' => true,
        ];
    }
}
