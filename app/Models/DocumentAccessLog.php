<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAccessLog extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const ACTION_VIEW = 'view';

    public const ACTION_DOWNLOAD = 'download';

    public const ACTION_UPLOAD = 'upload';

    public const ACTION_DELETE = 'delete';

    protected $fillable = [
        'company_id',
        'document_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
