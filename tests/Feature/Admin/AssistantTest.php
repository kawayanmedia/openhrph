<?php

use App\Ai\Agents\HrAssistant;
use App\Ai\Tools\ApproveRequests;
use App\Ai\Tools\RejectRequests;
use App\Enums\Assistant\RequestType;
use App\Livewire\Admin\Assistant\Chat;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use App\Models\ScheduleAdjustmentRequest;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Assistant\AttendanceHours;
use App\Services\Assistant\RequestApprovalService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\Request as ToolRequest;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Company::factory()->create(['id' => 1, 'name' => 'Kawayan Media']);

    Employee::factory()->createMany([
        [
            'id' => 1,
            'company_id' => 1,
            'office_id' => null,
            'employee_number' => '1001',
            'first_name' => 'Jordan',
            'last_name' => 'Reyes',
            'position' => 'Nurse',
        ],
        [
            'id' => 2,
            'company_id' => 1,
            'office_id' => null,
            'employee_number' => '1002',
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'position' => 'Analyst',
        ],
        [
            'id' => 3,
            'company_id' => 1,
            'office_id' => null,
            'employee_number' => '1003',
            'first_name' => 'Mia',
            'last_name' => 'Santos',
            'position' => 'Clerk',
        ],
    ]);

    // Jordan logged 70 hours, Ana 90 hours, Mia nothing at all.
    Timesheet::factory()->createMany([
        [
            'company_id' => 1,
            'employee_id' => 1,
            'employee_number' => '1001',
            'log_date' => '2026-03-03',
            'regular' => 40,
            'ot' => 30,
        ],
        [
            'company_id' => 1,
            'employee_id' => 2,
            'employee_number' => '1002',
            'log_date' => '2026-03-03',
            'regular' => 80,
            'ot' => 10,
        ],
    ]);

    $owner = User::factory()->create([
        'company_id' => 1,
        'owner' => true,
        'name' => 'HR Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($owner);

    app(TenantContext::class)->set(1);
});

function signedInAdmin(): User
{
    return User::query()->where('email', 'admin@example.com')->firstOrFail();
}

function pendingScheduleRequest(int $companyId = 1, array $overrides = []): int
{
    return ScheduleAdjustmentRequest::factory()->create(array_merge([
        'company_id' => $companyId,
        'employee_id' => 1,
        'date_filed' => '2026-03-01',
        'date_from' => '2026-03-03',
        'date_to' => '2026-03-04',
        'time_from' => '09:00:00',
        'time_to' => '18:00:00',
        'status' => 'Pending',
    ], $overrides))->id;
}

it('redirects guests away from the assistant page', function () {
    auth()->logout();

    $this->get(route('admin.assistant'))->assertRedirect(route('admin.login'));
});

it('renders the assistant page for admins', function () {
    $this->get(route('admin.assistant'))
        ->assertOk()
        ->assertSee('HR assistant');

    app(TenantContext::class)->set(1);

    Livewire::test(Chat::class)
        ->assertOk()
        ->assertSee('Ask in plain English');
});

it('reports the current cut-off when the company has an open pay period', function () {
    PayrollGroup::factory()->create(['id' => 7, 'company_id' => 1, 'name' => 'Semi-monthly']);
    PayrollDate::factory()->create([
        'company_id' => 1,
        'payroll_group_id' => 7,
        'payroll_date' => now()->toDateString(),
        'attendance_date_start' => now()->subDays(5)->toDateString(),
        'attendance_date_end' => now()->addDays(5)->toDateString(),
        'status' => 'open',
    ]);

    $period = app(AttendanceHours::class)->period();

    expect($period['source'])->toBe('current cut-off')
        ->and($period['start']->toDateString())->toBe(now()->subDays(5)->toDateString())
        ->and($period['payroll_group'])->toBe('Semi-monthly');
});

it('includes employees with no timesheets in the hours report', function () {
    $hours = app(AttendanceHours::class);
    $period = $hours->period('2026-03-01', '2026-03-31');

    $report = $hours->report($period, 80.0);

    $rows = collect($report['rows'])->keyBy('employee_number');

    expect($rows['1001']['hours'])->toBe(70.0)
        ->and($rows['1002']['hours'])->toBe(90.0)
        ->and($rows['1003']['hours'])->toBe(0.0)
        ->and($report['under_count'])->toBe(2)
        ->and($report['period']['start'])->toBe('2026-03-01');
});

it('tells the assistant the exact window it should quote', function () {
    $agent = new HrAssistant(signedInAdmin());

    expect((string) $agent->instructions())
        ->toContain('Kawayan Media')
        ->toContain('attendance_hours_report')
        ->toContain(now()->format('l, F j, Y'));
});

it('registers only the intended tools', function () {
    $tools = collect((new HrAssistant(signedInAdmin()))->tools())
        ->map(fn ($tool) => $tool->name())
        ->all();

    expect($tools)->toBe([
        'find_employees',
        'attendance_hours_report',
        'pending_requests',
        'request_details',
        'approve_requests',
        'reject_requests',
    ]);
});

it('stores a conversation and shows the reply after sending a message', function () {
    HrAssistant::fake(['Jordan Reyes is under 80 hours with 70.00 hours.']);

    Livewire::test(Chat::class)
        ->set('draft', 'who is under 80 hours?')
        ->call('send')
        ->assertSet('error', null)
        ->assertSet('draft', '')
        ->assertSee('Jordan Reyes is under 80 hours with 70.00 hours.')
        ->assertSee('who is under 80 hours?');

    expect(DB::table('agent_conversations')->count())->toBe(1)
        ->and(DB::table('agent_conversation_messages')->where('role', 'assistant')->count())->toBe(1);
});

it('asks for confirmation before running an approval tool', function () {
    $id = pendingScheduleRequest();

    HrAssistant::fake([
        AgentResponse::fakeWithPendingApprovals([
            new PendingApproval(
                id: 'call_abc',
                tool: 'approve_requests',
                arguments: ['type' => 'schedule', 'ids' => [$id]],
                reason: 'Approve 1 shift adjustment request for Reyes, Jordan.',
            ),
        ]),
    ]);

    Livewire::test(Chat::class)
        ->set('draft', "approve Jordan's pending shifts")
        ->call('send')
        ->assertSee('Confirmation required')
        ->assertSee('Approve 1 shift adjustment request for Reyes, Jordan.')
        ->assertDontSee('Approve 1 shift adjustment requests')
        ->assertSee('Reyes, Jordan')
        ->assertSet('pending.0.id', 'call_abc')
        ->assertSet('decisions', []);

    expect(DB::table('schedule_adjustment_requests')->where('id', $id)->value('status'))->toBe('Pending');
});

it('requires a decision for every pending approval before resuming', function () {
    $id = pendingScheduleRequest();

    HrAssistant::fake([
        AgentResponse::fakeWithPendingApprovals([
            new PendingApproval('call_1', 'approve_requests', ['type' => 'schedule', 'ids' => [$id]], 'Confirm'),
            new PendingApproval('call_2', 'reject_requests', ['type' => 'schedule', 'ids' => [$id], 'reason' => 'No'], 'Confirm'),
        ]),
        'All decisions applied.',
    ]);

    Livewire::test(Chat::class)
        ->set('draft', 'clean up the shift requests')
        ->call('send')
        ->call('decide', 'call_1', 'approve')
        ->call('applyDecisions')
        ->assertSet('error', 'Choose approve or reject for every pending action before continuing.');

    HrAssistant::assertNotPrompted(fn (AgentPrompt $prompt) => $prompt->hasApprovalDecisions());
});

it('resumes the paused turn with the collected decisions', function () {
    $id = pendingScheduleRequest();

    HrAssistant::fake([
        AgentResponse::fakeWithPendingApprovals([
            new PendingApproval('call_1', 'approve_requests', ['type' => 'schedule', 'ids' => [$id]], 'Confirm'),
        ]),
        'Approved 1 shift adjustment request.',
    ]);

    Livewire::test(Chat::class)
        ->set('draft', "approve Jordan's pending shifts")
        ->call('send')
        ->call('decide', 'call_1', 'approve')
        ->call('applyDecisions')
        ->assertSet('error', null)
        ->assertSee('Approved 1 shift adjustment request.');

    HrAssistant::assertPrompted(fn (AgentPrompt $prompt) => $prompt->hasApprovalDecisions()
        && $prompt->approvalDecisions->get('call_1')->action === 'approve');
});

it('approves pending schedule requests and keeps the tenant resolved', function () {
    $id = pendingScheduleRequest();

    $tool = app(ApproveRequests::class);

    $reason = $tool->shouldRequestApproval(new ToolRequest(['type' => 'schedule', 'ids' => [$id]]));

    expect($reason)->not->toBeNull()
        ->and($reason->reason)->toContain('Approve 1 shift adjustment request')
        ->and($reason->reason)->toContain('Reyes, Jordan');

    $result = json_decode((string) $tool->handle(new ToolRequest([
        'type' => 'schedule',
        'ids' => [$id],
        'remarks' => 'Covered by the assistant',
    ])), true);

    expect($result['status'])->toBe('approved')
        ->and($result['approved'])->toHaveCount(1)
        ->and($result['skipped'])->toBe([])
        ->and(DB::table('schedule_adjustment_requests')->where('id', $id)->value('status'))->toBe('Approved')
        ->and(DB::table('schedule_adjustment_requests')->where('id', $id)->value('remarks'))->toBe('Covered by the assistant')
        ->and(DB::table('timesheets')->where('employee_id', 1)->count())->toBeGreaterThan(0)
        ->and(app(TenantContext::class)->id())->toBe(1);
});

it('skips requests that are no longer pending', function () {
    $id = pendingScheduleRequest(overrides: ['status' => 'Approved']);

    $result = json_decode((string) app(ApproveRequests::class)->handle(new ToolRequest([
        'type' => 'schedule',
        'ids' => [$id],
    ])), true);

    expect($result['approved'])->toBe([])
        ->and($result['skipped'])->toBe([$id])
        ->and(DB::table('schedule_adjustment_requests')->where('id', $id)->value('status'))->toBe('Approved');
});

it('rejects a request with a reason', function () {
    $id = pendingScheduleRequest();

    $tool = app(RejectRequests::class);

    $reason = $tool->shouldRequestApproval(new ToolRequest([
        'type' => 'schedule',
        'ids' => [$id],
        'reason' => 'Shift overlaps a filed leave.',
    ]));

    expect($reason->reason)->toContain('Reject 1 shift adjustment request');

    $result = json_decode((string) $tool->handle(new ToolRequest([
        'type' => 'schedule',
        'ids' => [$id],
        'reason' => 'Shift overlaps a filed leave.',
    ])), true);

    $row = DB::table('schedule_adjustment_requests')->where('id', $id)->first();

    expect($result['status'])->toBe('rejected')
        ->and($row->status)->toBe('Rejected')
        ->and($row->reject_reason)->toBe('Shift overlaps a filed leave.')
        ->and($row->rejected_at)->not->toBeNull();
});

it('lists pending requests for one employee', function () {
    pendingScheduleRequest();
    pendingScheduleRequest(overrides: ['employee_id' => 2]);

    $rows = app(RequestApprovalService::class)->pending(RequestType::Schedule, 'Jordan', 10);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['employee'])->toBe('Reyes, Jordan')
        ->and($rows[0]['type'])->toBe('schedule');
});

it('only offers approvable tools for writes', function () {
    $tools = collect((new HrAssistant(signedInAdmin()))->tools());

    $approvable = $tools
        ->filter(fn ($tool) => $tool instanceof Approvable)
        ->map(fn ($tool) => $tool->name())
        ->values()
        ->all();

    expect($approvable)->toBe(['approve_requests', 'reject_requests']);
});

it('embeds the assistant panel in the admin layout', function () {
    $html = view('layouts.admin', ['title' => 'Dashboard', 'slot' => 'Dashboard content'])->render();

    expect($html)->toContain('Ask AI')
        ->and($html)->toContain('HR assistant')
        ->and($html)->toContain('Dashboard content');
});
