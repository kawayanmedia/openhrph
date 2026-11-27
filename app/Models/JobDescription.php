<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobDescription extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'position_id',
        'version',
        'title',
        'content',
        'effective_from',
        'effective_until',
        'is_current',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_current' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (JobDescription $description): void {
            if ($description->version === null) {
                $description->version = (int) static::query()
                    ->where('position_id', $description->position_id)
                    ->max('version') + 1;
            }
        });

        static::created(function (JobDescription $description): void {
            if (! $description->is_current) {
                return;
            }

            static::query()
                ->where('position_id', $description->position_id)
                ->whereKeyNot($description->getKey())
                ->update(['is_current' => false]);
        });
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
