<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An installation-wide setting such as the sector or the country profile.
 *
 * This model is intentionally not tenant-scoped: the row applies to every
 * company in the installation.
 */
class InstallationSetting extends Model
{
    protected $fillable = ['key', 'value'];
}
