<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="ui-card p-5 sm:p-6">
        <h3 class="text-lg font-semibold text-navy-900 dark:text-white">My Documents</h3>

        @if ($documents->isEmpty())
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No documents have been shared with you.</p>
        @else
            <table class="ui-table mt-4">
                <thead>
                    <tr>
                        <th class="ui-th">Document</th>
                        <th class="ui-th">Category</th>
                        <th class="ui-th">Issued</th>
                        <th class="ui-th">Expires</th>
                        <th class="ui-th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td class="ui-td">{{ $document->title }}</td>
                            <td class="ui-td">{{ $document->category ?? '—' }}</td>
                            <td class="ui-td">{{ $document->issued_at?->toFormattedDateString() ?? '—' }}</td>
                            <td class="ui-td">{{ $document->expires_at?->toFormattedDateString() ?? '—' }}</td>
                            <td class="ui-td text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('portal.documents.download', $document->getKey()) }}" class="ui-btn-secondary">Download</a>

                                    @if ($document->acknowledged_by_me)
                                        <span class="text-xs font-semibold text-teal-700 dark:text-teal-300">Acknowledged</span>
                                    @else
                                        <button type="button" wire:click="acknowledge({{ $document->getKey() }})" class="ui-btn-primary">
                                            Acknowledge
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
