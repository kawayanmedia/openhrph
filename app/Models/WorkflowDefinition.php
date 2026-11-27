<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'subject_type',
        'sector',
        'version',
        'configuration',
        'effective_from',
        'effective_until',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'configuration' => 'array',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<WorkflowInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('effective_from')
                ->orWhere('effective_from', '<=', now()->toDateString()))
            ->where(fn (Builder $query) => $query->whereNull('effective_until')
                ->orWhere('effective_until', '>=', now()->toDateString()));
    }
}
