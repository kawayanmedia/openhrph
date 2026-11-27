<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One protocol or sync event for a time clock.
 *
 * Kept as an audit trail: a push that never reached the app, an authentication
 * failure, or a sync that could not reach the device are visible here long
 * after the log line has rotated away.
 */
class AttendanceDeviceEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const KIND_PUSH = 'push';

    public const KIND_PULL = 'pull';

    public const KIND_IMPORT = 'import';

    public const KIND_TEST = 'test';

    public const KIND_ERROR = 'error';

    protected $fillable = [
        'company_id',
        'device_id',
        'serial_number',
        'protocol',
        'kind',
        'endpoint',
        'http_status',
        'records',
        'accepted',
        'duplicates',
        'unmatched',
        'message',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'device_id' => 'integer',
        'http_status' => 'integer',
        'records' => 'integer',
        'accepted' => 'integer',
        'duplicates' => 'integer',
        'unmatched' => 'integer',
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
