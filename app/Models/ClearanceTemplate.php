<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClearanceTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'version',
        'tasks',
        'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'tasks' => 'array',
        'is_active' => 'boolean',
    ];
}
