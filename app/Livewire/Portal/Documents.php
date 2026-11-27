<?php

namespace App\Livewire\Portal;

use App\Models\Document;
use App\Models\DocumentAcknowledgment;
use App\Services\Documents\DocumentService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Documents extends Component
{
    public string $title = 'My Documents';

    public $employee;

    public $documents;

    public function mount(): void
    {
        $this->employee = portal_user()?->employee;

        $this->load();
    }

    public function acknowledge(int $documentId, DocumentService $service): void
    {
        $document = Document::query()->findOrFail($documentId);

        abort_unless(Gate::allows('view', $document), 403);

        $service->acknowledge($document, portal_user(), $this->employee);

        $this->load();

        session()->flash('status', 'Document acknowledged.');
    }

    protected function load(): void
    {
        $this->documents = Document::query()
            ->where('owner_type', $this->employee?->getMorphClass())
            ->where('owner_id', $this->employee?->getKey())
            ->orderByDesc('id')
            ->get();

        $acknowledged = DocumentAcknowledgment::query()
            ->where('user_id', portal_user()?->getKey())
            ->pluck('document_id')
            ->all();

        $this->documents->each(fn (Document $document) => $document->setAttribute(
            'acknowledged_by_me',
            in_array($document->getKey(), $acknowledged, true),
        ));
    }

    public function render()
    {
        return view('livewire.portal.documents.index')
            ->title($this->title)
            ->layout('layouts.portal');
    }
}
