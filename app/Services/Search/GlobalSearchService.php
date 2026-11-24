<?php

namespace App\Services\Search;

use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Resources\Company as CompanyResource;
use App\Livewire\Admin\Resources\Compensation;
use App\Livewire\Admin\Resources\Department;
use App\Livewire\Admin\Resources\Employee;
use App\Livewire\Admin\Resources\JobVacancy;
use App\Livewire\Admin\Resources\LeaveRequest;
use App\Livewire\Admin\Resources\Office;
use App\Livewire\Admin\Resources\PayRoll;
use App\Livewire\Admin\Resources\User;
use App\Livewire\Admin\Support\Navigation;
use App\Models\Company;
use App\Models\User as UserModel;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the grouped results for the admin command palette.
 */
final class GlobalSearchService
{
    /**
     * A small, curated set of resources whose records are worth searching.
     *
     * @var array<string, class-string<resource>>
     */
    private const RECORD_RESOURCES = [
        'employee' => Employee::class,
        'company' => CompanyResource::class,
        'office' => Office::class,
        'department' => Department::class,
        'pay-roll' => PayRoll::class,
        'user' => User::class,
        'job-vacancy' => JobVacancy::class,
        'leave-request' => LeaveRequest::class,
        'compensation' => Compensation::class,
    ];

    /**
     * @return Collection<string, array<int, array{label: string, description: string, url: string}>>
     */
    public function search(UserModel $user, string $query, int $limitPerGroup = 5): Collection
    {
        $term = trim($query);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $results = collect()
            ->merge($this->quickActions($user, $term))
            ->merge($this->navigation($term))
            ->merge($this->records($user, $term, $limitPerGroup));

        return $results
            ->unique('url')
            ->groupBy('group')
            ->map(fn (Collection $group): array => $group->take($limitPerGroup * 2)->values()->all());
    }

    /**
     * @return array<int, array{group: string, label: string, description: string, url: string}>
     */
    private function quickActions(UserModel $user, string $term): array
    {
        $path = trim((string) config('admin.path'), '/');

        $actions = [
            ['label' => 'Dashboard', 'description' => 'Workspace overview', 'url' => "/{$path}/dashboard"],
            ['label' => 'Getting started', 'description' => 'Finish payroll setup', 'url' => "/{$path}/getting-started"],
            ['label' => 'AI assistant', 'description' => 'Ask the HR assistant', 'url' => "/{$path}/assistant"],
            ['label' => 'Data import', 'description' => 'Import employees, offices and payroll', 'url' => "/{$path}/imports"],
            ['label' => 'Government remittances', 'description' => 'Statutory reports and payments', 'url' => "/{$path}/reports/remittances"],
            ['label' => 'Employee master file', 'description' => 'All employees and 201 files', 'url' => "/{$path}/reports/employee-master-file"],
            ['label' => 'Companies', 'description' => 'Company settings', 'url' => "/{$path}/resources/company"],
            ['label' => 'Payroll accounts', 'description' => 'Company debit bank accounts', 'url' => "/{$path}/resources/company-bank"],
            ['label' => 'Bank file templates', 'description' => 'Payroll bank file layouts', 'url' => "/{$path}/resources/bank-file-template"],
            ['label' => 'General settings', 'description' => 'Company, logo and identifiers', 'url' => "/{$path}/settings/general"],
            ['label' => 'Payroll settings', 'description' => 'Payroll and statutory schedules', 'url' => "/{$path}/settings/payroll"],
            ['label' => 'Attendance settings', 'description' => 'Tardiness and overtime rules', 'url' => "/{$path}/settings/attendance"],
            ['label' => 'Country and statutory settings', 'description' => 'Jurisdiction and statutory defaults', 'url' => "/{$path}/settings/statutory"],
            ['label' => 'Sector settings', 'description' => 'Government or private policies', 'url' => "/{$path}/settings/sector"],
            ['label' => 'Leave settings', 'description' => 'Leave policies and balances', 'url' => "/{$path}/settings/leave"],
        ];

        if ($user->can('create', Company::class)) {
            $actions[] = ['label' => 'Create company', 'description' => 'Add another company under your account', 'url' => "/{$path}/resources/company/new"];
        }

        if (Employee::allows('create', Employee::$model)) {
            $actions[] = ['label' => 'Create employee', 'description' => 'Add an employee record', 'url' => "/{$path}/resources/employee/new"];
        }

        return collect($actions)
            ->filter(fn (array $action): bool => Str::contains(Str::lower($action['label'].' '.$action['description']), Str::lower($term)))
            ->map(fn (array $action): array => [
                'group' => 'Quick actions',
                'label' => $action['label'],
                'description' => $action['description'],
                'url' => $action['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{group: string, label: string, description: string, url: string}>
     */
    private function navigation(string $term): array
    {
        $results = [];

        foreach (Navigation::sections() as $section) {
            $items = collect();

            if (! empty($section['url'])) {
                $items->push($section);
            }

            $items = $items
                ->merge($section['items'] ?? [])
                ->merge($section['trailing'] ?? []);

            foreach ($section['groups'] ?? [] as $group) {
                $items = $items->merge($group['items'] ?? []);
            }

            foreach ($items as $item) {
                $label = (string) ($item['label'] ?? '');
                $url = (string) ($item['url'] ?? '');

                if ($label === '' || $url === '' || ! Str::contains(Str::lower($label), Str::lower($term))) {
                    continue;
                }

                $results[] = [
                    'group' => 'Navigation',
                    'label' => $label,
                    'description' => $section['label'] ?? 'Admin',
                    'url' => $url,
                ];
            }
        }

        return $results;
    }

    /**
     * @return array<int, array{group: string, label: string, description: string, url: string}>
     */
    private function records(UserModel $user, string $term, int $limit): array
    {
        $results = [];
        $tenantId = (int) (app(TenantContext::class)->id() ?? $user->company_id);

        foreach (self::RECORD_RESOURCES as $key => $class) {
            if (! $class::allows('viewAny', $class::$model)) {
                continue;
            }

            $model = $class::newModel();
            $query = $model->newQuery();
            $query = $class::indexQuery($query);

            if ($model instanceof UserModel && $tenantId > 0) {
                $query->forTenant($tenantId);
            }

            $columns = array_values(array_filter((array) $class::$search));

            if ($columns === []) {
                continue;
            }

            $this->applySearch($query, $columns, $term);

            foreach ($query->limit($limit)->get() as $record) {
                $results[] = [
                    'group' => $class::pluralLabel(),
                    'label' => (new $class($record))->title(),
                    'description' => $class::label().' · #'.$record->getKey(),
                    'url' => route('admin.resource.detail', ['resource' => $key, 'resourceId' => $record->getKey()]),
                ];
            }
        }

        return $results;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function applySearch(Builder $query, array $columns, string $term): void
    {
        $query->where(function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    $query->orWhereHas($relation, function (Builder $relationQuery) use ($relatedColumn, $term): void {
                        $relationQuery->where($relatedColumn, 'like', "%{$term}%");
                    });

                    continue;
                }

                $query->orWhere($column, 'like', "%{$term}%");
            }
        });
    }
}
