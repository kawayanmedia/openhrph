<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssumptionRecord extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'appointment_id',
        'oath_date',
        'assumption_date',
        'document_id',
        'witnessed_by',
        'remarks',
    ];

    protected $casts = [
        'oath_date' => 'date',
        'assumption_date' => 'date',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(AppointmentRecord::class, 'appointment_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witnessed_by');
    }
}
