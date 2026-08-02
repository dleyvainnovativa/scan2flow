<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentUploadRequest;
use App\Models\Area;
use App\Models\Document;
use App\Models\Template;
use App\Services\AuditService;
use App\Services\DocumentStorageService;
use App\Services\MetadataService;
use App\Services\PdfPageCounter;
use App\Support\DocumentVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentStorageService $storage,
        private MetadataService $metadata,
        private AuditService $audit,
        private PdfPageCounter $pageCounter,
    ) {}

    public function index(Request $request, Area $area)
    {
        $this->authorize('view', $area);

        $templates = $area->templates()->with('fields')->orderBy('name')->get();

        $templateId = $request->integer('template') ?: optional($templates->first())->id;
        $activeTemplate = $templates->firstWhere('id', $templateId);

        $documents = collect();
        $filters = (array) $request->input('f', []);

        if ($activeTemplate) {
            $query = Document::where('template_id', $activeTemplate->id)->with(['metadata.field']);

            // QA visibility: view-only users see only approved docs.
            DocumentVisibility::scope($query, $request->user());

            $fieldsByKey = $activeTemplate->fields->keyBy('key');
            foreach ($filters as $key => $value) {
                $value = trim((string) $value);
                if ($value === '' || ! $fieldsByKey->has($key)) {
                    continue;
                }
                $field = $fieldsByKey->get($key);
                $norm = $field->normalize($value);

                $query->whereHas('metadata', function ($q) use ($field, $norm, $value) {
                    $q->where('template_field_id', $field->id);
                    if (in_array($field->type, ['text'], true)) {
                        $q->where('value_norm', 'like', '%' . mb_strtolower($value) . '%');
                    } else {
                        $q->where('value_norm', $norm);
                    }
                });
            }

            $documents = $query->orderByDesc('created_at')->paginate(25)->withQueryString();
        }

        return view('documents.index', compact('area', 'templates', 'activeTemplate', 'documents', 'filters'));
    }

    public function show(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        // QA visibility: hide non-approved docs from users without edit/approve.
        abort_unless(DocumentVisibility::canSee($request->user(), $document), 404);

        $document->load(['area', 'template.fields', 'metadata.field', 'content']);

        $this->audit->log('viewed', $document, "Vio «{$document->title}»");

        $values = $document->metadata->mapWithKeys(fn($m) => [$m->field->key => $m->value]);

        $canApprove = DocumentVisibility::canApprove($request->user(), $document->area)
            && (int) $document->uploaded_by !== (int) $request->user()->id;

        return view('documents.show', compact('document', 'values', 'canApprove'));
    }

    public function store(DocumentUploadRequest $request, Template $template)
    {
        abort_unless(
            $request->user()->canOnArea($template->area, 'edit'),
            403,
            'Requiere permiso de edición en esta área.'
        );

        $document = DB::transaction(function () use ($request, $template) {
            $pdfPath = $this->storage->store($template, $request->file('pdf'));

            $xmlPath = null;
            if ($request->hasFile('xml')) {
                $base = pathinfo($pdfPath, PATHINFO_FILENAME);
                $xmlPath = $this->storage->store($template, $request->file('xml'), $base);
            }
            $pageCount = $this->pageCounter->count($request->file('pdf')->getRealPath());

            $document = Document::create([
                'area_id'     => $template->area_id,
                'template_id' => $template->id,
                'title'       => $request->input('title'),
                'page_count'  => $pageCount,
                'pdf_path'    => $pdfPath,
                'xml_path'    => $xmlPath,
                'ocr_status'  => 'not_applicable',
                'uploaded_by' => $request->user()->id,   // >>> ADD
                'status'      => 'pending',               // >>> ADD (explicit)
            ]);

            $this->metadata->sync($document, $request->input('metadata', []));

            if ($body = trim((string) $request->input('content_text', ''))) {
                $document->content()->create(['body' => $body, 'source' => 'manual']);
            }

            return $document;
        });

        $this->audit->log('uploaded', $document, "Cargó «{$document->title}»");

        return response()->json([
            'message'  => 'Documento cargado.',
            'redirect' => route('documents.show', $document),
        ], 201);
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $data = $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'metadata' => ['array'],
        ]);

        $missing = $this->metadata->missingRequired($document->template, $data['metadata'] ?? []);
        if (! empty($missing)) {
            return response()->json(['message' => reset($missing), 'errors' => $missing], 422);
        }

        DB::transaction(function () use ($document, $data) {
            $document->update(['title' => $data['title']]);
            $this->metadata->sync($document, $data['metadata'] ?? []);
        });

        if (in_array($document->status, ['rejected', 'pending'], true)) {
            $document->update([
                'status' => 'pending',
                'rejection_reason' => null,
            ]);
        }

        $this->audit->log('edited', $document, "Editó «{$document->title}»");

        return response()->json(['message' => 'Documento actualizado.']);
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        $title = $document->title;

        DB::transaction(function () use ($document) {
            $this->storage->deleteFiles($document);
            $document->delete();
        });

        $this->audit->log('deleted', null, "Eliminó «{$title}»");

        return response()->json(['message' => 'Documento eliminado.']);
    }

    public function stream(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);
        abort_unless(DocumentVisibility::canSee($request->user(), $document), 404);

        abort_unless($document->pdf_path, 404);
        $disk = Storage::disk($this->storage->disk());
        abort_unless($disk->exists($document->pdf_path), 404);

        return $disk->response($document->pdf_path, null, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $document->title . '.pdf"',
        ]);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('download', $document);
        abort_unless(DocumentVisibility::canSee($request->user(), $document), 404);

        abort_unless($document->pdf_path, 404);
        $disk = Storage::disk($this->storage->disk());
        abort_unless($disk->exists($document->pdf_path), 404);

        $this->audit->log('downloaded', $document, "Descargó «{$document->title}»");

        return $disk->download($document->pdf_path, $document->title . '.pdf');
    }
}
