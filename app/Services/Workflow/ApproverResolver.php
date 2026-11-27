<?php

namespace App\Services\Workflow;

use App\Models\Employee;
use App\Models\Group;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\WorkflowDelegation;
use App\Models\WorkflowTask;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns declarative assignee definitions into concrete users and answers
 * whether a user may act on a task.
 */
class ApproverResolver
{
    /**
     * @param  array{type: string, value: int|string}  $assignee
     * @return Collection<int, User>
     */
    public function resolve(array $assignee): Collection
    {
        $users = match ($assignee['type']) {
            'user' => User::query()->whereKey((int) $assignee['value'])->get(),
            'employee' => $this->forEmployee((int) $assignee['value']),
            'group' => $this->forGroup((string) $assignee['value']),
            'role' => $this->forRole((string) $assignee['value']),
            'unit' => $this->forUnit((int) $assignee['value']),
            default => collect(),
        };

        return $users->unique('id')->values();
    }

    public function canAct(WorkflowTask $task, User $user): bool
    {
        if ((int) $task->assigned_user_id === (int) $user->getKey()) {
            return true;
        }

        if ($task->assigned_user_id !== null && $this->hasDelegation((int) $task->assigned_user_id, (int) $user->getKey())) {
            return true;
        }

        return match ($task->assignee_type) {
            'group' => $this->userInGroup($user, (string) $task->assignee_label),
            'role' => $this->userInRole($user, (string) $task->assignee_label),
            'unit' => $this->userInUnit($user, (int) $task->assignee_id),
            default => false,
        };
    }

    public function hasDelegation(int $delegatorId, int $delegateeId): bool
    {
        return WorkflowDelegation::withoutTenant()
            ->where('delegator_id', $delegatorId)
            ->where('delegatee_id', $delegateeId)
            ->current()
            ->exists();
    }

    protected function forEmployee(int $employeeId): Collection
    {
        $query = Employee::withoutTenant()->whereKey($employeeId);

        if (($companyId = app(TenantContext::class)->id()) !== null) {
            $query->where('company_id', $companyId);
        }

        $userId = $query->value('user_id');

        return $userId === null
            ? collect()
            : $this->tenantUsers()->whereKey((int) $userId)->get();
    }

    protected function forGroup(string $name): Collection
    {
        $groupQuery = Group::withoutTenant()->where('name', $name);

        if (($companyId = app(TenantContext::class)->id()) !== null) {
            $groupQuery->where('company_id', $companyId);
        }

        $groupIds = $groupQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($groupIds === []) {
            return collect();
        }

        $userIds = User::query()
            ->whereIn('group_id', $groupIds)
            ->pluck('id')
            ->merge(
                DB::table('company_user')->whereIn('group_id', $groupIds)->pluck('user_id')
            )
            ->unique()
            ->all();

        return $this->tenantUsers()->whereIn('id', $userIds)->get();
    }

    protected function forRole(string $name): Collection
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return collect();
        }

        $roleIds = DB::table('roles')->where('name', $name)->pluck('id');

        if ($roleIds->isEmpty()) {
            return collect();
        }

        $userIds = DB::table('model_has_roles')
            ->whereIn('role_id', $roleIds)
            ->where('model_type', User::class)
            ->pluck('model_id')
            ->unique()
            ->all();

        return $this->tenantUsers()->whereIn('id', $userIds)->get();
    }

    protected function forUnit(int $unitId): Collection
    {
        $unit = OrganizationUnit::withoutTenant()->find($unitId);

        if ($unit === null) {
            return collect();
        }

        $unitIds = array_merge([$unit->getKey()], $unit->descendantIds());

        $userIds = Employee::withoutTenant()
            ->whereIn('organization_unit_id', $unitIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->all();

        return $this->tenantUsers()->whereIn('id', $userIds)->get();
    }

    /**
     * @return Builder<User>
     */
    protected function tenantUsers()
    {
        $query = User::query();

        if (($companyId = app(TenantContext::class)->id()) !== null) {
            $query->forTenant($companyId);
        }

        return $query;
    }

    protected function userInGroup(User $user, string $name): bool
    {
        return $user->groupForTenant()?->name === $name;
    }

    protected function userInRole(User $user, string $name): bool
    {
        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles')) {
            return false;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->getKey())
            ->where('roles.name', $name)
            ->exists();
    }

    protected function userInUnit(User $user, int $unitId): bool
    {
        $unit = OrganizationUnit::withoutTenant()->find($unitId);

        if ($unit === null) {
            return false;
        }

        $employeeUnitId = Employee::withoutTenant()
            ->where('user_id', $user->getKey())
            ->value('organization_unit_id');

        if ($employeeUnitId === null) {
            return false;
        }

        return in_array((int) $employeeUnitId, array_merge([$unit->getKey()], $unit->descendantIds()), true);
    }
}
