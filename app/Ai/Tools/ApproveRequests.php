<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ApprovableRequestTool;
use App\Enums\Assistant\RequestType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;
use Stringable;

class ApproveRequests extends ApprovableRequestTool
{
    public function name(): string
    {
        return 'approve_requests';
    }

    public function description(): Stringable|string
    {
        return 'Approve one or more pending requests of a single type. The user must confirm before anything is written, so call this with the exact ids returned by pending_requests.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', RequestType::values())],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:128'],
        ]);

        $type = RequestType::from($data['type']);

        $result = $this->requests->approve($type, $data['ids'], $data['remarks'] ?? null);

        return json_encode([
            'status' => 'approved',
            'type' => $type->value,
            'approved' => $result['approved'],
            'skipped' => $result['skipped'],
            'summary' => sprintf(
                'Approved %d %s. %s',
                count($result['approved']),
                Str::lcfirst(count($result['approved']) === 1 ? $type->label() : $type->pluralLabel()),
                $result['skipped'] === []
                    ? 'Nothing was skipped.'
                    : 'Skipped id(s): '.implode(', ', $result['skipped']).' (not pending or not found).',
            ),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('The request type to approve.')
                ->enum(RequestType::values())
                ->required(),
            'ids' => $schema->array()
                ->description('Request ids to approve, taken from pending_requests.')
                ->items($schema->integer()->min(1))
                ->min(1)
                ->required(),
            'remarks' => $schema->string()
                ->description('Optional remarks stored on the approved request.'),
        ];
    }

    protected function verb(): string
    {
        return 'Approve';
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return $this->approvalReason($request);
    }
}
