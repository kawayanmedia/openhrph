<?php

namespace App\Http\Controllers\Admin;

use App\Models\Document;
use App\Services\Documents\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentDownloadController
{
    public function __invoke(Request $request, Document $document, DocumentService $documents)
    {
        abort_unless(Auth::user()?->can('download', $document), 403);

        return $documents->download($document, Auth::user());
    }
}
