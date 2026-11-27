<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\TenantScopedAuthorization;

class DocumentPolicy
{
    use TenantScopedAuthorization;

    public function view(User $user, Document $document): bool
    {
        if (! $this->sameTenant($user, $document)) {
            return false;
        }

        if (! $document->isSensitive()) {
            return true;
        }

        return $this->canManageSensitive($user, $document);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        if (! $this->sameTenant($user, $document)) {
            return false;
        }

        return $this->canManageSensitive($user, $document) || ! $document->isSensitive();
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    protected function canManageSensitive(User $user, Document $document): bool
    {
        if ($user->owner || $user->ownsAnyCompany()) {
            return true;
        }

        if ((int) $document->uploaded_by === (int) $user->getKey()) {
            return true;
        }

        $permissions = $user->groupForTenant()?->permissions ?? [];

        return ! empty($permissions['Documents']);
    }
}
