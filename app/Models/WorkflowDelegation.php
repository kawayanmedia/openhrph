<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowDelegation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'delegator_id',
        'delegatee_id',
        'starts_at',
        'ends_at',
        'reason',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegatee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegatee_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', now()->toDateString())
            ->where('ends_at', '>=', now()->toDateString());
    }
}
