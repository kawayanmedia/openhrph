<?php

namespace App\Jurisdictions\Philippines\Government\Models;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Children extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pds_children';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
