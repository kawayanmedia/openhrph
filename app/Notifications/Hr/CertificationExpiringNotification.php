<?php

namespace App\Notifications\Hr;

use App\Models\EmployeeCertification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CertificationExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public EmployeeCertification $certification) {}

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
            'title' => 'Certification expiring',
            'message' => $this->certification->title.' for '.($this->certification->employee?->fullname ?? 'an employee')
                .' expires on '.$this->certification->expires_at?->toFormattedDateString().'.',
            'employee_certification_id' => $this->certification->getKey(),
            'url' => route('admin.resource.detail', [
                'resource' => 'employee-certification',
                'resourceId' => $this->certification->getKey(),
            ]),
        ];
    }
}
