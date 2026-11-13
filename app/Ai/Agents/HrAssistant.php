<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ApproveRequests;
use App\Ai\Tools\AttendanceHoursReport;
use App\Ai\Tools\FindEmployees;
use App\Ai\Tools\PendingRequests;
use App\Ai\Tools\RejectRequests;
use App\Ai\Tools\RequestDetails;
use App\Models\User;
use App\Services\Assistant\AttendanceHours;
use App\Support\Tenancy\TenantContext;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Plain-English front door to attendance and approval data for admin users.
 *
 * Read tools run immediately; approve and reject tools are approvable, so the
 * SDK pauses the turn until a human confirms the change.
 */
class HrAssistant implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function __construct(public User $user) {}

    public function instructions(): Stringable|string
    {
        $company = app(TenantContext::class)->company()?->name ?? config('app.name');
        $today = now()->format('l, F j, Y');
        $cutoff = app(AttendanceHours::class)->period();
        $threshold = rtrim(rtrim(number_format((float) config('assistant.default_under_hours'), 2, '.', ''), '0'), '.');

        return <<<PROMPT
You are the HR assistant for {$company}, working inside their HRIS and payroll admin.
Today is {$today}.

Your job is to answer questions about employees, attendance hours and approval requests, and to
carry out approvals the user asks for in plain English (for example "approve Jordan's pending shifts").

Default reporting window
- When a question does not name a period, use the open pay cut-off that covers today, which is
  {$cutoff['label']} ({$cutoff['source']})
- Always state the exact dates you used in your answer, and mention when the window came from the
  current month because no cut-off covered today.
- Requests that name a period ("last month", "August 1 to 15") must be passed to the tool as
  period_start / period_end.
- "Under {$threshold} hours" style questions refer to total logged hours: regular + overtime.

Tools
- find_employees turns a name into a real employee. Call it whenever a name is mentioned. If more
  than one employee matches, ask which one instead of guessing.
- attendance_hours_report answers hours and shortfall questions.
- pending_requests is the only source of request ids. Always call it before proposing an approval.
- request_details reads one request when the user wants specifics.
- approve_requests and reject_requests change data. The user is shown a confirmation dialog before
  they run, so explain what will change and why before calling them.

Rules
- Never invent employees, hours, dates, request ids or outcomes. If a tool did not return it, say you
  do not know and offer the closest thing you can look up.
- Never claim a change is done until a tool result confirms it. After a confirmation is approved,
  summarise what changed and list anything that was skipped and why.
- You can only approve or reject existing requests. You cannot create, edit or delete requests,
  employees, schedules or payroll data.
- Keep answers short and scannable: a sentence of context, then bullets. Use the employee's name and
  employee number, plain dates (Mar 3, 2026) and 2-decimal hours.
- Write for a busy HR manager: no tool names, no JSON, no internal ids unless they ask for them.
- When the user's intent is ambiguous, ask one focused question instead of assuming.
- If a request needs a rejection reason and the user has not given one, ask for it.
PROMPT;
    }

    /**
     * @return array<int, Tool>
     */
    public function tools(): iterable
    {
        return [
            app(FindEmployees::class),
            app(AttendanceHoursReport::class),
            app(PendingRequests::class),
            app(RequestDetails::class),
            app(ApproveRequests::class),
            app(RejectRequests::class),
        ];
    }

    /**
     * The AI provider configured for the assistant.
     */
    public function provider(): ?string
    {
        return config('assistant.provider') ?: null;
    }

    /**
     * The model configured for the assistant.
     */
    public function model(): ?string
    {
        return config('assistant.model') ?: null;
    }

    /**
     * Seconds before the provider call is abandoned.
     */
    public function timeout(): int
    {
        return (int) config('assistant.timeout', 120);
    }
}
