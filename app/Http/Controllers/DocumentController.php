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
use App\Services\PageBalanceService;
use App\Exceptions\InsufficientPagesException;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentStorageService $storage,
        private MetadataService $metadata,
        private AuditService $audit,
        private PdfPageCounter $pageCounter,
        private PageBalanceService $balance,
        private \App\Contracts\SearchEngine $search,
    ) {}

    /**
     * Global documents view: every document the user may see across ALL their
     * areas, with global filters + pagination. Two modes on one page:
     *
     *   • BROWSE (no keyword) → Eloquent query, real pagination, and the full
     *     filter set (area, template, status, date range, and — once a template
     *     is picked — its dynamic metadata fields, reusing the area-view logic).
     *
     *   • SEARCH (keyword present) → delegates to the SearchEngine (FULLTEXT
     *     over metadata + OCR), returning the top ranked hits with snippets.
     *     The engine caps results and does its own status-visibility, so this
     *     mode is not deep-paginated — it's "refine your query".
     *
     * ⚠️ Browse mode composes access in the required order: constrain to the
     * user's viewable areas FIRST, then DocumentVisibility::scope() for status.
     */
    public function all(Request $request)
    {
        $user = $request->user();

        // Areas the user may view (admins: all tenant areas). Also the option
        // list for the area filter + the guard for browse-mode scoping.
        $areas = $user->isAdmin()
            ? Area::orderBy('name')->get()
            : $user->areas()->where('can_view', true)->orderBy('name')->get();
        $viewableAreaIds = $areas->pluck('id');

        // Global filters (apply in both modes where supported).
        $q          = trim((string) $request->input('q', ''));
        $areaId     = $request->integer('area_id') ?: null;
        $templateId = $request->integer('template_id') ?: null;
        $status     = $request->input('status');
        $from       = $request->input('from');
        $to         = $request->input('to');

        if ($status !== null && ! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = null;
        }

        // Guard: an area_id filter must be one the user can actually view.
        if ($areaId && ! $viewableAreaIds->contains($areaId)) {
            $areaId = null;
        }

        // Templates for the metadata-filter tier (only when a template chosen).
        $activeTemplate = null;
        if ($templateId) {
            $activeTemplate = Template::with('fields')
                ->whereIn('area_id', $viewableAreaIds)
                ->find($templateId);
            if (! $activeTemplate) {
                $templateId = null; // not in a viewable area → ignore
            }
        }

        $metaFilters = (array) $request->input('f', []);

        // ── SEARCH MODE ──────────────────────────────────────────────────
        if ($q !== '') {
            $engineFilters = array_filter([
                'area_id'     => $areaId,
                'template_id' => $templateId,
            ]);

            $results = $viewableAreaIds->isEmpty()
                ? collect()
                : $this->search->search($q, $user, $engineFilters);

            return view('documents.all', [
                'mode'           => 'search',
                'results'        => $results,
                'documents'      => null,
                'areas'          => $areas,
                'activeTemplate' => $activeTemplate,
                'q'              => $q,
                'areaId'         => $areaId,
                'templateId'     => $templateId,
                'status'         => $status,
                'from'           => $from,
                'to'             => $to,
                'metaFilters'    => $metaFilters,
            ]);
        }

        // ── BROWSE MODE ──────────────────────────────────────────────────
        if ($viewableAreaIds->isEmpty()) {
            $documents = Document::whereRaw('1 = 0')->paginate(25); // empty paginator
        } else {
            // 1) Area constraint FIRST (see DocumentVisibility warning).
            $query = Document::whereIn('area_id', $viewableAreaIds)
                ->with(['area', 'template']);

            if ($areaId) {
                $query->where('area_id', $areaId);
            }
            if ($templateId) {
                $query->where('template_id', $templateId);
            }
            if ($status) {
                $query->where('status', $status);
            }
            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }

            // Dynamic metadata filters — only meaningful with a chosen template.
            if ($activeTemplate) {
                $fieldsByKey = $activeTemplate->fields->keyBy('key');
                foreach ($metaFilters as $key => $value) {
                    $value = trim((string) $value);
                    if ($value === '' || ! $fieldsByKey->has($key)) {
                        continue;
                    }
                    $field = $fieldsByKey->get($key);
                    $norm  = $field->normalize($value);

                    $query->whereHas('metadata', function ($sub) use ($field, $norm, $value) {
                        $sub->where('template_field_id', $field->id);
                        if (in_array($field->type, ['text'], true)) {
                            $sub->where('value_norm', 'like', '%' . mb_strtolower($value) . '%');
                        } else {
                            $sub->where('value_norm', $norm);
                        }
                    });
                }
            }

            // 2) Status visibility SECOND.
            DocumentVisibility::scope($query, $user);

            $documents = $query->orderByDesc('created_at')->paginate(25)->withQueryString();
        }

        return view('documents.all', [
            'mode'           => 'browse',
            'results'        => null,
            'documents'      => $documents,
            'areas'          => $areas,
            'activeTemplate' => $activeTemplate,
            'q'              => $q,
            'areaId'         => $areaId,
            'templateId'     => $templateId,
            'status'         => $status,
            'from'           => $from,
            'to'             => $to,
            'metaFilters'    => $metaFilters,
        ]);
    }

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

        $pageCount = $this->pageCounter->count($request->file('pdf')->getRealPath()) ?? 0;
        if ($pageCount > 0 && ! $this->balance->canCover($pageCount, $request->user()->tenant_id)) {
            return response()->json([
                'message' => 'Tu cuenta no tiene páginas suficientes para subir este documento.',
            ], 402); // 402 Payment Required
        }

        $document = DB::transaction(function () use ($request, $template, $pageCount) {
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

            if ($pageCount > 0) {
                $this->balance->debit(
                    pages: $pageCount,
                    tenantId: $request->user()->tenant_id,
                    subjectType: \App\Models\Document::class,
                    subjectId: $document->id,
                    causedBy: $request->user()->id,
                    note: 'Carga manual: ' . $document->title,
                );
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
