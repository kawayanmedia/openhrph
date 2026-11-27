<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScopedAuthorization;

class LearningCoursePolicy
{
    use TenantScopedAuthorization;
}
