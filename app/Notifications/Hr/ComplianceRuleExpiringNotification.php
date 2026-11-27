<?php

namespace App\Notifications\Hr;

use App\Models\ComplianceRule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplianceRuleExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public ComplianceRule $rule) {}

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
        return [
            'title' => 'Compliance rule expires soon',
            'message' => $this->rule->name.' ('.$this->rule->code.') is effective until '
                .$this->rule->effective_until?->toFormattedDateString().'.',
            'compliance_rule_id' => $this->rule->getKey(),
            'url' => route('admin.resource.detail', [
                'resource' => 'compliance-rule',
                'resourceId' => $this->rule->getKey(),
            ]),
        ];
    }
}
