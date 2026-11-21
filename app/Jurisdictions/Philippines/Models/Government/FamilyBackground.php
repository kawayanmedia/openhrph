<?php

namespace App\Jurisdictions\Philippines\Models\Government;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyBackground extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pds_family_background';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
