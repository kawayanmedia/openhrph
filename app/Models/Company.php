<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    use HasFactory;

    protected $casts = ['hidden_menu' => 'array'];

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

    public function bonuses()
    {
        return $this->hasMany(Bonus::class);
    }

    public function allowances()
    {
        return $this->hasMany(Allowance::class);
    }
}
