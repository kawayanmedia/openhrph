<?php

namespace App\Livewire\Traits;

trait ConfirmDelete
{
    // To show/hide the modal
    public bool $viewingModal = false;

    // The information currently being displayed in the modal
    public $currentModal;

    // This will call listener in UserIndex so that confirm message display
    public function confirmDelete()
    {
        $this->viewingModal = true;
        // $this->dispatch('confirmDelete', $this->selectedKeys); // Use in the future
    }

    public function modalsView(): string
    {
        return 'livewire.modals.confirm_modal';
    }
}
