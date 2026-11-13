<?php

namespace App\Models\Government;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoluntaryWork extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pds_voluntary_works';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
