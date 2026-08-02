<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\AuditService;
use App\Support\DocumentVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentReviewController extends Controller
{
    public function __construct(private AuditService $audit) {}

    /** Approve a document. Approver must differ from uploader. */
    public function approve(Request $request, Document $document)
    {
        $user = $request->user();
        $this->authorizeReview($user, $document);

        // Separation of duties: the uploader cannot approve their own document.
        if ($document->uploaded_by && (int) $document->uploaded_by === (int) $user->id) {
            abort(403, 'No puedes aprobar un documento que tú mismo subiste. Debe revisarlo otra persona.');
        }

        $document->update([
            'status'           => 'approved',
            'rejection_reason' => null,
            'reviewed_at'      => now(),
            'reviewed_by'      => $user->id,
        ]);

        $this->audit->log('approved', $document, 'Aprobado por usuario #' . $user->id);

        return response()->json(['message' => 'Documento aprobado.', 'status' => 'approved']);
    }

    /** Reject a document with a reason. Approver must differ from uploader. */
    public function reject(Request $request, Document $document)
    {
        $user = $request->user();
        $this->authorizeReview($user, $document);

        if ($document->uploaded_by && (int) $document->uploaded_by === (int) $user->id) {
            abort(403, 'No puedes rechazar un documento que tú mismo subiste.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [], ['reason' => 'motivo']);

        $document->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['reason'],
            'reviewed_at'      => now(),
            'reviewed_by'      => $user->id,
        ]);

        $this->audit->log('rejected', $document, 'Rechazado por usuario #' . $user->id . ': ' . $validated['reason']);

        return response()->json(['message' => 'Documento rechazado.', 'status' => 'rejected']);
    }

    /** Gate: user must have approve permission on the document's area. */
    private function authorizeReview($user, Document $document): void
    {
        abort_unless(
            DocumentVisibility::canApprove($user, $document->area),
            403,
            'No tienes permiso para revisar documentos en esta área.'
        );
    }
}
