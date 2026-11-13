<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ActionEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [];

    protected $table = 'action_events';
}
