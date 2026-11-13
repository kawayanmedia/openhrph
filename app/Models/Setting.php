<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * The table key is only unique per company, so the auto-incrementing `id`
     * stays the primary key: updates must never span companies.
     */
    public $timestamps = false;

    public $fillable = ['key', 'value', 'company_id'];

    public $companyId;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function setValueAttribute($value)
    {
        $this->attributes['value'] = is_array($value) || $value instanceof \JsonSerializable
            ? json_encode($value)
            : $value;

        if ($tenantId = app(TenantContext::class)->id()) {
            $this->attributes['company_id'] = $tenantId;
        }
    }

    public function getValueAttribute($value)
    {
        $originalCasts = $this->casts;

        if ($this->hasCast($this->key)) {
            $value = $this->castAttribute($this->key, $value);
        }

        $this->casts = $originalCasts;

        return $value;
    }

    public static function getValueForKey($key)
    {
        $setting = static::where('key', $key)->get()->first();

        return isset($setting) ? $setting->value : null;
    }
}
