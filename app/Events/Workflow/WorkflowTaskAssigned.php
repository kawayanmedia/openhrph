<?php

namespace App\Events\Workflow;

use App\Models\WorkflowTask;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowTaskAssigned
{
    use Dispatchable;

    public function __construct(public WorkflowTask $task) {}
}
