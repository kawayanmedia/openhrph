<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Scopes\FilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyBank extends Model
{
    use BelongsToTenant;
    use FilterScope;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'companies_banks';

    protected $fillable = ['company_id', 'name', 'code', 'account_number'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
