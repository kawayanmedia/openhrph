<?php

namespace App\Jobs;

use App\Models\Payroll;
use App\Services\Payroll\Engine\EngineManager;
use App\Services\Payroll\Engine\PayrollEngineException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public Payroll $payroll) {}

    /**
     * Run small payrolls immediately so the operator sees the result without a
     * queue worker; larger runs are queued.
     */
    public static function dispatchFor(Payroll $payroll): void
    {
        $threshold = (int) config('payroll.engine.inline_threshold', 50);
        $employees = $payroll->payrollGroup?->employees()->count() ?? 0;

        if ($employees <= $threshold) {
            dispatch_sync(new static($payroll));

            return;
        }

        static::dispatch($payroll);
    }

    public function handle(EngineManager $manager): void
    {
        $this->payroll->refresh();

        $this->payroll->forceFill([
            'status' => Payroll::STATUS_RUNNING,
            'failed_reason' => null,
        ])->save();

        $employees = $this->payroll->payrollGroup?->employees()->count() ?? 0;

        try {
            $result = $manager->engine()->process($this->payroll);
        } catch (PayrollEngineException $e) {
            $this->fail($e);

            return;
        }

        $this->payroll->forceFill([
            'status' => Payroll::STATUS_PROCESSED,
            'engine_version' => $result->engineVersion,
            'processed_at' => now(),
            'processed_employees' => $result->employees,
            'total_employees' => $employees ?: $result->employees,
            'failed_reason' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $this->payroll->forceFill([
            'status' => 'failed',
            'failed_reason' => $exception?->getMessage(),
        ])->save();
    }
}
