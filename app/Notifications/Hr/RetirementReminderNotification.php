<?php

namespace App\Notifications\Hr;

use App\Models\RetirementCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RetirementReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public RetirementCase $case, public string $reason = 'upcoming') {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $employee = $this->case->employee?->fullname ?? 'An employee';

        return [
            'title' => $this->reason === 'claim' ? 'External retirement claim needs follow-up' : 'Retirement case due',
            'message' => $this->reason === 'claim'
                ? 'A claim for '.$employee.' has not been checked recently.'
                : $employee.' is expected to retire on '.$this->case->proposed_retirement_date?->toFormattedDateString().'.',
            'retirement_case_id' => $this->case->getKey(),
            'url' => route('admin.resource.detail', [
                'resource' => 'retirement-case',
                'resourceId' => $this->case->getKey(),
            ]),
        ];
    }
}
