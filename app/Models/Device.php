<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'ip_address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {

        static::created(function (Device $model) {
            set_current_timestamp($model);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * Find a device by its plain text integration key, regardless of tenant.
     */
    public static function findByApiKey(string $plainKey): ?self
    {
        return static::withoutTenant()
            ->where('api_key', hash('sha256', $plainKey))
            ->first();
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->saveQuietly();
    }
}
