<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tests\Fixtures\TenantRecordFactory;

#[UseFactory(TenantRecordFactory::class)]
class TenantRecord extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'tenant_records';

    protected $guarded = [];

    public $timestamps = false;
}
