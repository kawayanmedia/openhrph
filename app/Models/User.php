<?php

namespace App\Models;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Ai\Concerns\HasConversations;
use Laravel\Pennant\Concerns\HasFeatures;

// use Laravel\Nova\Auth\Impersonatable;

class User extends Authenticatable
{
    use HasConversations, HasFactory, HasFeatures, Notifiable;

    private ?Collection $companyMemberships = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // https://filamentphp.com/docs/2.x/admin/users#authorizing-access-to-the-admin-panel
    /**
     * The permission group the user belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function canAccessFilament(): bool
    {
        return true;
    }

    // public function teams(): BelongsToMany
    // {
    //     return $this->belongsToMany(Team::class);
    // }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->teams->contains($tenant);
    }

    public function getFilamentName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (User $model) {
            set_current_timestamp($model);
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Every company the user is a member of.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot(['is_owner', 'group_id'])
            ->withTimestamps();
    }

    /**
     * Memberships loaded for this request, tolerating installations whose
     * schema predates the company_user table.
     */
    public function companyMemberships(): Collection
    {
        if ($this->companyMemberships !== null) {
            return $this->companyMemberships;
        }

        try {
            return $this->companyMemberships = $this->companies()->get();
        } catch (QueryException) {
            return $this->companyMemberships = collect();
        }
    }

    /**
     * Whether the user belongs to the given company.
     */
    public function belongsToCompany(int $companyId): bool
    {
        if ($this->companyMemberships()->isNotEmpty()) {
            return $this->companyMemberships()->contains('id', $companyId);
        }

        return (int) $this->company_id === $companyId;
    }

    /**
     * The company currently in use for this request.
     */
    public function activeCompany(): ?Company
    {
        $tenantId = app(TenantContext::class)->id() ?? $this->company_id;

        if ($tenantId === null) {
            return $this->companyMemberships()->first();
        }

        return $this->companyMemberships()->firstWhere('id', (int) $tenantId)
            ?? $this->companyMemberships()->firstWhere('id', (int) $this->company_id)
            ?? $this->companyMemberships()->first();
    }

    /**
     * The permission group for the active (or given) company.
     */
    public function groupForTenant(?int $companyId = null): ?Group
    {
        $companyId ??= app(TenantContext::class)->id() ?? $this->company_id;

        $membership = $companyId === null
            ? null
            : $this->companyMemberships()->firstWhere('id', (int) $companyId);
        $groupId = $membership?->pivot?->group_id;

        if ($groupId === null && ($companyId === null || (int) $this->company_id === (int) $companyId || $this->company_id === null)) {
            $groupId = $this->group_id;
        }

        if ($groupId === null) {
            return null;
        }

        if ($this->relationLoaded('group') && $this->group !== null
            && ($this->group->getKey() === null || $this->group->getKey() === (int) $groupId)) {
            return $this->group;
        }

        $query = Group::query()->withoutTenant()->whereKey($groupId);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->first() ?? ($this->relationLoaded('group') ? $this->group : null);
    }

    /**
     * Scope users down to a company, including multi-company members.
     */
    public function scopeForTenant($query, int $companyId)
    {
        return $query->where(function ($query) use ($companyId): void {
            $query->where('company_id', $companyId)
                ->orWhereHas('companies', fn ($memberships) => $memberships->whereKey($companyId));
        });
    }

    public function oldUser()
    {
        return true;
    }

    public function newUser()
    {
        return true;
    }
}
