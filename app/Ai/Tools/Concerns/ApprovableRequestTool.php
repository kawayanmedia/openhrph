<?php

namespace App\Ai\Tools\Concerns;

use App\Enums\Assistant\RequestType;
use App\Services\Assistant\RequestApprovalService;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Shared behaviour for tools that change request records, so both the approval
 * reason shown to the user and the execution path agree on what will change.
 */
abstract class ApprovableRequestTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(protected RequestApprovalService $requests) {}

    /**
     * The verb used in the confirmation reason ("Approve" / "Reject").
     */
    abstract protected function verb(): string;

    /**
     * Build the human readable reason attached to the approval request.
     */
    protected function approvalReason(Request $request): Approval
    {
        $type = $this->typeFrom($request);
        $ids = $this->idsFrom($request);

        if ($type === null || $ids === []) {
            return Approval::required('This will change request records. Confirm the details before continuing.');
        }

        $description = $this->requests->describe($type, $ids);
        $count = count($description['rows']);

        if ($count === 0) {
            return Approval::required(sprintf(
                'No %s still need a decision — nothing will change.',
                $type->pluralLabel(),
            ));
        }

        $names = collect($description['rows'])
            ->pluck('employee')
            ->filter()
            ->unique()
            ->take(3)
            ->implode(', ');

        $reason = sprintf(
            '%s %d %s%s.',
            $this->verb(),
            $count,
            Str::lcfirst($count === 1 ? $type->label() : $type->pluralLabel()),
            $names !== '' ? ' for '.$names : '',
        );

        if ($description['missing'] !== []) {
            $reason .= ' '.count($description['missing']).' id(s) are no longer pending and will be skipped.';
        }

        return Approval::required($reason);
    }

    protected function typeFrom(Request $request): ?RequestType
    {
        $value = $request['type'] ?? null;

        return is_string($value) ? RequestType::tryFrom($value) : null;
    }

    /**
     * @return array<int, int>
     */
    protected function idsFrom(Request $request): array
    {
        $ids = $request['ids'] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
