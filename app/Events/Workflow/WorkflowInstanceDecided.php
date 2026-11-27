<?php

namespace App\Events\Workflow;

use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowInstanceDecided
{
    use Dispatchable;

    public function __construct(
        public WorkflowInstance $instance,
        public ?User $actor,
        public string $decision,
    ) {}
}
