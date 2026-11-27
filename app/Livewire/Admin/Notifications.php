<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Notifications extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAllRead(): void
    {
        Auth::user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    public function markRead(string $notificationId): void
    {
        $notification = Auth::user()?->notifications()->find($notificationId);

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $this->redirect($url, navigate: false);

            return;
        }

        $this->open = false;
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.admin.notifications', [
            'notifications' => $user?->notifications()->latest()->limit(10)->get() ?? collect(),
            'unreadCount' => $user?->unreadNotifications()->count() ?? 0,
        ]);
    }
}
