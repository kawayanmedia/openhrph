<?php

namespace App\Ai\Tools;

use App\Enums\Assistant\RequestType;
use App\Services\Assistant\RequestApprovalService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PendingRequests implements Tool
{
    public function __construct(protected RequestApprovalService $requests) {}

    public function name(): string
    {
        return 'pending_requests';
    }

    public function description(): Stringable|string
    {
        return 'List requests that are still waiting for approval, newest first, optionally filtered by employee or request type. Always call this before proposing an approval so the ids and effects are real.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'in:'.implode(',', RequestType::values())],
            'employee' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $type = isset($data['type']) ? RequestType::from($data['type']) : null;
        $limit = (int) ($data['limit'] ?? config('assistant.max_result_rows'));

        $rows = $this->requests->pending($type, $data['employee'] ?? null, $limit);

        return json_encode([
            'count' => count($rows),
            'counts_by_type' => $this->requests->pendingCounts()->all(),
            'requests' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Restrict to one request type.')
                ->enum(RequestType::values()),
            'employee' => $schema->string()
                ->description('Filter by employee name or employee number.'),
            'limit' => $schema->integer()
                ->description('Maximum rows to return (default 30).')
                ->min(1)->max(100),
        ];
    }
}
