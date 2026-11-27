<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'document_id',
        'version',
        'media_id',
        'file_name',
        'mime_type',
        'size',
        'checksum',
        'uploaded_by',
        'notes',
    ];

    protected $casts = [
        'version' => 'integer',
        'size' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
