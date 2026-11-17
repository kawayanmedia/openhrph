<?php

namespace App\Models\Government;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkExperience extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pds_work_experience';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
