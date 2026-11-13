<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ApprovableRequestTool;
use App\Enums\Assistant\RequestType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;
use Stringable;

class RejectRequests extends ApprovableRequestTool
{
    public function name(): string
    {
        return 'reject_requests';
    }

    public function description(): Stringable|string
    {
        return 'Reject one or more pending requests of a single type, recording the reason. The user must confirm before anything is written.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', RequestType::values())],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
            'reason' => ['required', 'string', 'max:128'],
        ]);

        $type = RequestType::from($data['type']);

        $result = $this->requests->reject($type, $data['ids'], $data['reason']);

        return json_encode([
            'status' => 'rejected',
            'type' => $type->value,
            'reason' => $data['reason'],
            'rejected' => $result['rejected'],
            'skipped' => $result['skipped'],
            'summary' => sprintf(
                'Rejected %d %s. %s',
                count($result['rejected']),
                Str::lcfirst(count($result['rejected']) === 1 ? $type->label() : $type->pluralLabel()),
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
                ->description('The request type to reject.')
                ->enum(RequestType::values())
                ->required(),
            'ids' => $schema->array()
                ->description('Request ids to reject, taken from pending_requests.')
                ->items($schema->integer()->min(1))
                ->min(1)
                ->required(),
            'reason' => $schema->string()
                ->description('Why the request is being rejected. Stored on the request and returned to the employee.')
                ->required(),
        ];
    }

    protected function verb(): string
    {
        return 'Reject';
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return $this->approvalReason($request);
    }
}
