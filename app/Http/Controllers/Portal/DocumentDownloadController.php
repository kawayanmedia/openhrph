<?php

namespace App\Http\Controllers\Portal;

use App\Models\Document;
use App\Services\Documents\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DocumentDownloadController
{
    public function __invoke(Request $request, Document $document, DocumentService $documents)
    {
        abort_unless(Gate::forUser(Auth::guard('portal')->user())->allows('download', $document), 403);

        return $documents->download($document, Auth::guard('portal')->user());
    }
}
