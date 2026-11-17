<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reference extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pds_references';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
