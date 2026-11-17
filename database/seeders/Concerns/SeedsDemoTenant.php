<?php

namespace Database\Seeders\Concerns;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Closure;
use Database\Seeders\CompanySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

trait SeedsDemoTenant
{
    public const DEMO_COMPANY_SLUG = 'demo';

    /**
     * Resolve the demo tenant created by {@see CompanySeeder}.
     */
    protected function demoCompany(): Company
    {
        return Company::query()
            ->where('slug', self::DEMO_COMPANY_SLUG)
            ->firstOrFail();
    }

    /**
     * Run a callback with the demo tenant resolved in the context.
     *
     * @template TReturn
     *
     * @param  Closure(Company): TReturn  $callback
     * @return TReturn
     */
    protected function runForDemoTenant(Closure $callback): mixed
    {
        return app(TenantContext::class)->run($this->demoCompany(), $callback);
    }

    /**
     * Create or update a row using natural keys instead of mass assignment.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    protected function upsertModel(string $modelClass, array $keys, array $values = []): Model
    {
        /** @var Model $model */
        $model = $modelClass::query()->withoutGlobalScopes()->where($keys)->first() ?? new $modelClass;

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $values['deleted_at'] = null;
        }

        $model->forceFill($keys + $values);
        $model->save();

        return $model;
    }
}
