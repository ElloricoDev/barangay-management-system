<?php

namespace App\Http\Controllers;

use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Document;
use App\Models\Resident;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentsController extends Controller
{
    public function archiveIndex(Request $request): Response
    {
        $filters = $this->filters($request);
        $documents = $this->queryDocuments($filters)
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/DocumentArchive', [
            'filters' => $filters,
            'documents' => $documents,
        ]);
    }

    public function uploadIndex(Request $request): Response
    {
        $filters = $this->filters($request);

        $documents = $this->queryDocuments($filters)
            ->where('uploaded_by', $request->user()->id)
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Staff/UploadDocuments', [
            'filters' => $filters,
            'documents' => $documents,
            'residents' => Resident::query()
                ->select(['id', 'first_name', 'last_name'])
                ->whereNull('archived_at')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(300)
                ->get(),
            'certificates' => Certificate::query()
                ->select(['id', 'type', 'status', 'resident_id'])
                ->with('resident:id,first_name,last_name')
                ->latest()
                ->limit(200)
                ->get(),
            'blotters' => Blotter::query()
                ->select(['id', 'complainant_name', 'respondent_name', 'status'])
                ->latest()
                ->limit(200)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'module' => ['nullable', 'in:resident,certificate,blotter,other'],
            'resident_id' => ['nullable', 'exists:residents,id'],
            'certificate_id' => ['nullable', 'exists:certificates,id'],
            'blotter_id' => ['nullable', 'exists:blotters,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        $storedPath = $file->store('documents', 'public');

        $document = Document::create([
            'resident_id' => $validated['resident_id'] ?? null,
            'certificate_id' => $validated['certificate_id'] ?? null,
            'blotter_id' => $validated['blotter_id'] ?? null,
            'uploaded_by' => $request->user()->id,
            'title' => $validated['title'],
            'module' => $validated['module'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($storedPath),
            'disk' => 'public',
            'path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => (int) $file->getSize(),
            'notes' => $validated['notes'] ?? null,
            'status' => 'submitted',
        ]);

        AuditLogger::log(
            $request,
            'document.upload',
            Document::class,
            $document->id,
            null,
            $document->only(['title', 'module', 'resident_id', 'certificate_id', 'blotter_id', 'original_name', 'file_size'])
        );

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function approve(Request $request, Document $document)
    {
        if ($document->status === 'approved') {
            return redirect()->back()->with('error', 'Document is already approved.');
        }

        $before = $document->only(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'updated_at']);

        $document->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        AuditLogger::log(
            $request,
            'document.approve',
            Document::class,
            $document->id,
            $before,
            $document->only(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'updated_at'])
        );

        return redirect()->back()->with('success', 'Document approved.');
    }

    public function reject(Request $request, Document $document)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($document->status === 'rejected') {
            return redirect()->back()->with('error', 'Document is already rejected.');
        }

        $before = $document->only(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'updated_at']);

        $document->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['reason'] ?? null,
        ]);

        AuditLogger::log(
            $request,
            'document.reject',
            Document::class,
            $document->id,
            $before,
            $document->only(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'updated_at'])
        );

        return redirect()->back()->with('success', 'Document rejected.');
    }

    public function download(Request $request, Document $document)
    {
        if (! $request->user()->hasPermission('documents.download') && $document->uploaded_by !== $request->user()->id) {
            abort(403, 'Insufficient permission.');
        }

        if (! Storage::disk($document->disk)->exists($document->path)) {
            return redirect()->back()->with('error', 'Document file not found.');
        }

        AuditLogger::log(
            $request,
            'document.download',
            Document::class,
            $document->id,
            null,
            $document->only(['title', 'module', 'original_name'])
        );

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, Document $document)
    {
        if (! $request->user()->hasPermission('documents.delete') && $document->uploaded_by !== $request->user()->id) {
            abort(403, 'Insufficient permission.');
        }

        $before = $document->only([
            'title',
            'module',
            'resident_id',
            'certificate_id',
            'blotter_id',
            'original_name',
            'path',
            'file_size',
        ]);

        if (Storage::disk($document->disk)->exists($document->path)) {
            Storage::disk($document->disk)->delete($document->path);
        }

        $documentId = $document->id;
        $document->delete();

        AuditLogger::log(
            $request,
            'document.delete',
            Document::class,
            $documentId,
            $before,
            null
        );

        return redirect()->back()->with('success', 'Document deleted successfully.');
    }

    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'module' => trim((string) $request->query('module', '')),
            'status' => trim((string) $request->query('status', '')),
            'sort' => trim((string) $request->query('sort', 'created_at')),
            'direction' => strtolower(trim((string) $request->query('direction', 'desc'))) === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function queryDocuments(array $filters)
    {
        $sortable = ['title', 'module', 'status', 'original_name', 'file_size', 'created_at'];
        $sort = in_array($filters['sort'], $sortable, true) ? $filters['sort'] : 'created_at';

        return Document::query()
            ->with([
                'resident:id,first_name,last_name',
                'uploader:id,name',
                'reviewer:id,name',
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('resident', function ($residentQuery) use ($search) {
                            $residentQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['module'] !== '', function ($query) use ($filters) {
                $query->where('module', $filters['module']);
            })
            ->when($filters['status'] !== '', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->orderBy($sort, $filters['direction']);
    }
}
