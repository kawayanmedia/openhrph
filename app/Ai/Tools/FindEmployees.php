<?php

namespace App\Ai\Tools;

use App\Models\Employee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FindEmployees implements Tool
{
    public function name(): string
    {
        return 'find_employees';
    }

    public function description(): Stringable|string
    {
        return 'Look up employees by name or employee number. Use this to turn a first name into a single employee before asking follow-up questions or approving anything.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = (int) ($data['limit'] ?? 10);
        $search = '%'.trim($data['query']).'%';

        $employees = Employee::query()
            ->where(function ($query) use ($search): void {
                $query->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('employee_number', 'like', $search)
                    ->orWhereRaw("concat(last_name, ', ', first_name) like ?", [$search]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'position', 'office_id', 'date_resigned']);

        return json_encode([
            'count' => $employees->count(),
            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => (int) $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->fullname,
                'position' => $employee->position,
                'status' => $employee->date_resigned ? 'resigned on '.$employee->date_resigned->format('M j, Y') : 'active',
            ])->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Full or partial first name, last name, "Last, First", or employee number.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of employees to return (default 10).')
                ->min(1)->max(50),
        ];
    }
}
