<?php

namespace App\Models\Government;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Employee;

class Education extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pds_education_background';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
