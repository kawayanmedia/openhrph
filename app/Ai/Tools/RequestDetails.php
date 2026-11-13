<?php

namespace App\Ai\Tools;

use App\Enums\Assistant\RequestType;
use App\Services\Assistant\RequestApprovalService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RequestDetails implements Tool
{
    public function __construct(protected RequestApprovalService $requests) {}

    public function name(): string
    {
        return 'request_details';
    }

    public function description(): Stringable|string
    {
        return 'Read a single request by type and id, including its employee, dates and current status.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', RequestType::values())],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $details = $this->requests->find(RequestType::from($data['type']), (int) $data['id']);

        if ($details === null) {
            return 'No matching request was found in this company.';
        }

        return json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('The request type.')
                ->enum(RequestType::values())
                ->required(),
            'id' => $schema->integer()
                ->description('The request id from pending_requests.')
                ->min(1)
                ->required(),
        ];
    }
}
