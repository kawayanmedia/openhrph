<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceRating extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'performance_review_id',
        'criterion',
        'score',
        'weight',
        'comments',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'weight' => 'decimal:2',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }
}
