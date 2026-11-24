<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankFileTemplate extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'bank_file_templates';

    protected $fillable = [
        'company_id',
        'name',
        'bank_name',
        'delimiter',
        'columns',
        'header_rows',
        'footer_rows',
        'amount_in_centavos',
        'date_format',
        'include_control_total',
        'is_active',
    ];

    protected $casts = [
        'columns' => 'array',
        'header_rows' => 'array',
        'footer_rows' => 'array',
        'amount_in_centavos' => 'bool',
        'include_control_total' => 'bool',
        'is_active' => 'bool',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
