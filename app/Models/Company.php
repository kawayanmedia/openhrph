<?php

namespace App\Models;

use App\Support\Installation\Installation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;

class Company extends Model
{
    use HasFactory;

    protected $casts = ['hidden_menu' => 'array'];

    /**
     * The installer chooses Government or Private once for the whole
     * installation, so every company is pinned to that sector as it is saved.
     */
    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            if ($company->user_id === null) {
                $company->user_id = Auth::id() ?? Auth::guard('nova')->id();
            }
        });

        static::saving(function (Company $company): void {
            if ($sector = Installation::sector()) {
                $company->type = $sector;
            }

            $company->country_code ??= Installation::countryCode();
        });
    }

    /**
     * Users that belong to this company.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot(['is_owner', 'group_id'])
            ->withTimestamps();
    }

    public function offices()
    {
        return $this->hasMany(Office::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function payrollGroups()
    {
        return $this->hasMany(PayrollGroup::class);
    }
}
