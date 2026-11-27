<?php

namespace App\Models;

use App\Enums\DeviceProtocol;
use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    public const SYNC_OK = 'ok';

    public const SYNC_FAILED = 'failed';

    public const SYNC_SKIPPED = 'skipped';

    protected $fillable = [
        'name',
        'serial_number',
        'protocol',
        'ip_address',
        'port',
        'timezone',
        'credentials',
        'config',
        'office_id',
        'is_active',
        'sync_enabled',
        'sync_interval_minutes',
    ];

    protected $casts = [
        'protocol' => DeviceProtocol::class,
        'credentials' => 'encrypted:array',
        'config' => 'array',
        'port' => 'integer',
        'office_id' => 'integer',
        'sync_enabled' => 'boolean',
        'sync_interval_minutes' => 'integer',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
        'push_key',
        'credentials',
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

    public function deviceEvents(): HasMany
    {
        return $this->hasMany(AttendanceDeviceEvent::class);
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

    /**
     * Find a device by the serial number it reports over the push protocol.
     */
    public static function findBySerial(string $serial): ?self
    {
        $serial = trim($serial);

        if ($serial === '') {
            return null;
        }

        return static::withoutTenant()
            ->where('serial_number', $serial)
            ->first();
    }

    /**
     * Devices the sync command may pull from, across all tenants.
     *
     * @return Builder<self>
     */
    public function scopePullable(Builder $query): Builder
    {
        return $query
            ->where('sync_enabled', true)
            ->where('is_active', true)
            ->whereIn('protocol', collect(DeviceProtocol::cases())
                ->filter(fn (DeviceProtocol $protocol): bool => $protocol->isPullable())
                ->map(fn (DeviceProtocol $protocol): string => $protocol->value)
                ->all());
    }

    public function protocolEnum(): DeviceProtocol
    {
        return $this->protocol instanceof DeviceProtocol
            ? $this->protocol
            : DeviceProtocol::tryFromValue((string) $this->protocol) ?? DeviceProtocol::GenericJson;
    }

    public function isPullable(): bool
    {
        return $this->protocolEnum()->isPullable();
    }

    public function resolvedPort(): ?int
    {
        return $this->port ?: $this->protocolEnum()->defaultPort();
    }

    public function resolvedTimezone(): string
    {
        $timezone = trim((string) $this->timezone);

        return $timezone !== '' ? $timezone : (string) config('app.timezone', 'Asia/Manila');
    }

    /**
     * Whether the device is due for another pull.
     */
    public function isDueForSync(?int $intervalMinutes = null): bool
    {
        if (! $this->sync_enabled || ! $this->is_active || ! $this->isPullable()) {
            return false;
        }

        if ($this->last_synced_at === null) {
            return true;
        }

        $interval = max(1, $intervalMinutes ?? (int) ($this->sync_interval_minutes ?: 5));

        return $this->last_synced_at->lte(now()->subMinutes($interval));
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    public function markSynced(?string $cursor = null, ?string $message = null): void
    {
        $this->forceFill([
            'last_synced_at' => now(),
            'last_sync_status' => self::SYNC_OK,
            'last_sync_message' => $message,
            'last_pull_cursor' => $cursor ?? $this->last_pull_cursor,
        ])->saveQuietly();
    }

    public function markSyncFailed(string $message): void
    {
        $this->forceFill([
            'last_synced_at' => now(),
            'last_sync_status' => self::SYNC_FAILED,
            'last_sync_message' => mb_substr($message, 0, 1000),
        ])->saveQuietly();
    }
}
