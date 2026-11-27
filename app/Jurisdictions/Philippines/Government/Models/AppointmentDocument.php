<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentDocument extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'appointment_id',
        'document_id',
        'document_type',
        'version',
        'notes',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(AppointmentRecord::class, 'appointment_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
