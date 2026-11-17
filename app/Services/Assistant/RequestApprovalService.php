<?php

namespace App\Services\Assistant;

use App\Enums\Assistant\RequestType;
use App\Jobs\ApproveAttendanceJob;
use App\Jobs\ApproveLeaveJob;
use App\Jobs\ApproveOvertimeJob;
use App\Jobs\ApproveScheduleJob;
use App\Jobs\ApproveUndertimeJob;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Single place where the assistant reads and acts on approval requests.
 *
 * The approve/reject behaviour intentionally mirrors the existing admin and
 * Nova actions so an assistant decision produces the same side effects.
 */
class RequestApprovalService
{
    /**
     * Requests awaiting a decision.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pending(?RequestType $type = null, ?string $employee = null, int $limit = 30): array
    {
        $types = $type === null ? RequestType::cases() : [$type];

        $rows = [];

        foreach ($types as $requestType) {
            $query = $requestType->query()
                ->where('status', STATUS_PENDING)
                ->with('employee')
                ->orderByDesc('id')
                ->limit($limit);

            if (filled($employee)) {
                $query->whereHas('employee', function ($query) use ($employee): void {
                    $search = '%'.$employee.'%';

                    $query->where(function ($query) use ($search): void {
                        $query->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('employee_number', 'like', $search)
                            ->orWhereRaw("concat(last_name, ', ', first_name) like ?", [$search]);
                    });
                });
            }

            foreach ($query->get() as $request) {
                $rows[] = ['type' => $requestType->value, 'type_label' => $requestType->label()]
                    + $requestType->detail($request);
            }
        }

        usort($rows, fn (array $a, array $b) => $b['id'] <=> $a['id']);

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(RequestType $type, int $id): ?array
    {
        $request = $type->query()->with('employee')->find($id);

        return $request ? $type->detail($request) : null;
    }

    /**
     * Human readable rows for a confirmation card.
     *
     * @param  array<int, int|string>  $ids
     * @return array{type: string, label: string, rows: array<int, array<string, mixed>>, missing: array<int, int>}
     */
    public function describe(RequestType $type, array $ids): array
    {
        $ids = $this->normalizeIds($ids);

        $requests = $type->query()
            ->whereIn('id', $ids)
            ->where('status', STATUS_PENDING)
            ->with('employee')
            ->get()
            ->keyBy(fn (Model $request) => (int) $request->getKey());

        $rows = [];
        $missing = [];

        foreach ($ids as $id) {
            $request = $requests->get($id);

            if ($request === null) {
                $missing[] = $id;

                continue;
            }

            $rows[] = $type->detail($request);
        }

        return [
            'type' => $type->value,
            'label' => $type->pluralLabel(),
            'rows' => $rows,
            'missing' => $missing,
        ];
    }

    /**
     * Approve pending requests, skipping ids that are missing or already decided.
     *
     * @param  array<int, int|string>  $ids
     * @return array{approved: array<int, array<string, mixed>>, skipped: array<int, int>}
     */
    public function approve(RequestType $type, array $ids, ?string $remarks = null): array
    {
        return $this->preservingTenant(function () use ($type, $ids, $remarks): array {
            $ids = $this->normalizeIds($ids);

            $requests = $type->query()
                ->whereIn('id', $ids)
                ->where('status', STATUS_PENDING)
                ->with('employee')
                ->get();

            foreach ($requests as $request) {
                $this->approveOne($type, $request, $remarks);
            }

            return [
                'approved' => $requests->map(fn (Model $request) => $type->detail($request))->values()->all(),
                'skipped' => array_values(array_diff($ids, $requests->pluck('id')->map(fn ($id) => (int) $id)->all())),
            ];
        });
    }

    /**
     * Reject pending requests, skipping ids that are missing or already decided.
     *
     * @param  array<int, int|string>  $ids
     * @return array{rejected: array<int, array<string, mixed>>, skipped: array<int, int>}
     */
    public function reject(RequestType $type, array $ids, ?string $reason = null): array
    {
        return $this->preservingTenant(function () use ($type, $ids, $reason): array {
            $ids = $this->normalizeIds($ids);

            $requests = $type->query()
                ->whereIn('id', $ids)
                ->where('status', STATUS_PENDING)
                ->with('employee')
                ->get();

            foreach ($requests as $request) {
                $this->rejectOne($type, $request, $reason);
            }

            return [
                'rejected' => $requests->map(fn (Model $request) => $type->detail($request))->values()->all(),
                'skipped' => array_values(array_diff($ids, $requests->pluck('id')->map(fn ($id) => (int) $id)->all())),
            ];
        });
    }

    /**
     * Dispatched jobs fire JobProcessed, which forgets the tenant for the rest
     * of the request, so restore it before returning to the caller.
     */
    protected function preservingTenant(callable $callback): mixed
    {
        $tenant = app(TenantContext::class);
        $previous = $tenant->id();

        try {
            return $callback();
        } finally {
            if ($previous !== null && $tenant->id() !== $previous) {
                $tenant->set($previous);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function approveOne(RequestType $type, Model $request, ?string $remarks): void
    {
        if ($type === RequestType::OfficialBusiness) {
            $request->approved_user_id = $request->employee?->id;
            $request->status = STATUS_APPROVED;
            $request->approved_at = now();
            $request->remarks = $remarks;
            $request->save();

            return;
        }

        $request->status = STATUS_APPROVED;
        $request->approved_at = now();
        $request->save();

        $approver = $request->employee;

        match ($type) {
            RequestType::Leave => ApproveLeaveJob::dispatch($request, $approver),
            RequestType::Schedule => ApproveScheduleJob::dispatch($request, $approver, (object) ['remarks' => $remarks]),
            RequestType::Overtime => ApproveOvertimeJob::dispatch($request, $approver, (object) ['remarks' => $remarks]),
            RequestType::Undertime => ApproveUndertimeJob::dispatch($request, $approver, (object) ['remarks' => $remarks]),
            RequestType::Attendance => ApproveAttendanceJob::dispatch($request, $approver, (object) ['remarks' => $remarks]),
        };
    }

    protected function rejectOne(RequestType $type, Model $request, ?string $reason): void
    {
        if ($type === RequestType::Attendance) {
            // The timesheet request table has no rejection columns.
            $request->status = STATUS_REJECTED;
            $request->save();

            return;
        }

        $request->status = STATUS_REJECTED;
        $request->reject_reason = $reason;
        $request->rejected_at = now();
        $request->remarks = $reason;
        $request->approved_at = null;
        $request->save();
    }

    /**
     * The pending count per request type, for dashboard-ish answers.
     *
     * @return Collection<string, int>
     */
    public function pendingCounts(): Collection
    {
        return collect(RequestType::cases())
            ->mapWithKeys(fn (RequestType $type) => [
                $type->value => $type->query()->where('status', STATUS_PENDING)->count(),
            ]);
    }
}
