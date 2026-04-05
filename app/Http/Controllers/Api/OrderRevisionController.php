<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRevision;
use App\Models\OrderRevisionFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class OrderRevisionController extends Controller
{
    public function index(Request $request, Order $order)
    {
        if (!$this->canAccessOrder($request->user(), $order)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $maxRevisions = $this->maxRevisionsForOrder($order);
        $revisions = $order->revisions()
            ->with('files')
            ->orderBy('revision_number')
            ->get();

        return response()->json([
            'order_id' => $order->id,
            'max_revisions' => $maxRevisions,
            'used_revisions' => $revisions->count(),
            'remaining_revisions' => max($maxRevisions - $revisions->count(), 0),
            'revisions' => $revisions,
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $user = $request->user();

        if (!$this->isFreelancerOwner($user, $order)) {
            return response()->json(['error' => 'Solo el freelancer del servicio puede enviar revisiones'], 403);
        }

        if (in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true)) {
            return response()->json([
                'error' => 'No puedes enviar revisiones en una orden completada o cancelada',
            ], 422);
        }

        $validated = $request->validate([
            'freelancer_note' => 'required|string|max:3000',
            'files' => 'nullable|array',
            'files.*' => 'file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,webp,zip,rar',
        ]);

        $maxRevisions = $this->maxRevisionsForOrder($order);
        $currentCount = $order->revisions()->count();
        if ($currentCount >= $maxRevisions) {
            return response()->json([
                'error' => 'La orden ya alcanzó el máximo de revisiones permitidas',
                'max_revisions' => $maxRevisions,
            ], 422);
        }

        $revision = DB::transaction(function () use ($request, $order, $validated, $currentCount) {
            $revision = $order->revisions()->create([
                'revision_number' => $currentCount + 1,
                'freelancer_note' => $validated['freelancer_note'],
                'submitted_at' => now(),
            ]);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $uploadedFile) {
                    $path = $uploadedFile->store('order_revision_files', 'public');
                    $revision->files()->create([
                        'path' => $path,
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'mime_type' => $uploadedFile->getClientMimeType(),
                        'size' => $uploadedFile->getSize(),
                    ]);
                }
            }

            return $revision->load('files');
        });

        return response()->json([
            'message' => 'Revisión enviada correctamente',
            'revision' => $revision,
        ], 201);
    }

    public function addFeedback(Request $request, Order $order, OrderRevision $orderRevision)
    {
        $user = $request->user();

        if (!$this->isOrderOwner($user, $order) && (int) $user->role_id !== 4) {
            return response()->json([
                'error' => 'Solo el cliente/empresa de la orden puede responder la revisión',
            ], 403);
        }

        if ((int) $orderRevision->order_id !== (int) $order->id) {
            return response()->json(['error' => 'La revisión no pertenece a esa orden'], 422);
        }

        $validated = $request->validate([
            'client_feedback' => 'required|string|max:3000',
        ]);

        $orderRevision->update([
            'client_feedback' => $validated['client_feedback'],
            'feedback_at' => now(),
        ]);

        return response()->json([
            'message' => 'Feedback registrado correctamente',
            'revision' => $orderRevision->fresh('files'),
        ]);
    }

    public function downloadFile(
        Request $request,
        Order $order,
        OrderRevision $orderRevision,
        OrderRevisionFile $orderRevisionFile
    ) {
        if (!$this->canAccessOrder($request->user(), $order)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ((int) $orderRevision->order_id !== (int) $order->id) {
            return response()->json(['error' => 'La revisión no pertenece a esa orden'], 422);
        }

        $revision = $order->revisions()->whereKey($orderRevision->id)->first();
        if (!$revision) {
            return response()->json(['error' => 'La revisión no pertenece a esa orden'], 422);
        }

        if ((int) $orderRevisionFile->order_revision_id !== (int) $orderRevision->id) {
            return response()->json(['error' => 'El archivo no pertenece a esa revisión'], 422);
        }

        $file = $revision->files()->whereKey($orderRevisionFile->id)->first();
        if (!$file) {
            return response()->json(['error' => 'El archivo no pertenece a esa revisión'], 422);
        }

        if (!Storage::disk('public')->exists($file->path)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        $absolutePath = Storage::disk('public')->path($file->path);
        $response = response()->download(
            $absolutePath,
            $file->original_name,
            [
                'Content-Type' => $file->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]
        );

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->original_name
        );

        return $response;
    }

    private function canAccessOrder($user, Order $order): bool
    {
        if ((int) $user->role_id === 4) {
            return true;
        }

        if ($this->isOrderOwner($user, $order)) {
            return true;
        }

        return $this->isFreelancerOwner($user, $order);
    }

    private function isOrderOwner($user, Order $order): bool
    {
        return (int) $order->user_id === (int) $user->id;
    }

    private function isFreelancerOwner($user, Order $order): bool
    {
        return (int) optional($order->service->freelancerProfile)->user_id === (int) $user->id;
    }

    private function maxRevisionsForOrder(Order $order): int
    {
        $max = (int) optional($order->service)->revisions;
        if ($max < 1) {
            return 1;
        }

        if ($max > 3) {
            return 3;
        }

        return $max;
    }
}
