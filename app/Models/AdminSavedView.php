<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named set of index search, filter, sort and page-size values.
 */
class AdminSavedView extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'admin_saved_views';

    protected $fillable = [
        'company_id',
        'user_id',
        'resource',
        'name',
        'query',
    ];

    protected $casts = [
        'query' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
