<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderFinalDelivery;
use App\Models\OrderFinalDeliveryFile;
use App\Notifications\OrderDeliveredToCustomerNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class OrderFinalDeliveryController extends Controller
{
    public function show(Request $request, Order $order)
    {
        if (!$this->canAccessOrder($request->user(), $order)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $finalDelivery = $order->finalDelivery()->with('files')->first();
        if (!$finalDelivery) {
            return response()->json(['error' => 'La orden no tiene entrega final aún'], 404);
        }

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
            'final_delivery' => $finalDelivery,
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $user = $request->user();

        if (!$this->isFreelancerOwner($user, $order)) {
            return response()->json(['error' => 'Solo el freelancer del servicio puede enviar la entrega final'], 403);
        }

        if ($order->status !== Order::STATUS_IN_PROGRESS) {
            return response()->json(['error' => 'La entrega final solo se puede enviar cuando la orden está en progreso'], 422);
        }

        if ($order->finalDelivery()->exists()) {
            return response()->json(['error' => 'La orden ya tiene una entrega final registrada'], 422);
        }

        $maxRevisions = $this->maxRevisionsForOrder($order);
        $usedRevisions = $order->revisions()->count();
        if ($usedRevisions < $maxRevisions) {
            return response()->json([
                'error' => 'Debes completar todas las revisiones antes de la entrega final',
                'max_revisions' => $maxRevisions,
                'used_revisions' => $usedRevisions,
            ], 422);
        }

        $validated = $request->validate([
            'final_note' => 'required|string|max:3000',
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:30720|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,webp,zip,rar',
        ]);

        $finalDelivery = DB::transaction(function () use ($request, $order, $validated) {
            $finalDelivery = $order->finalDelivery()->create([
                'final_note' => $validated['final_note'],
                'submitted_at' => now(),
            ]);

            foreach ($request->file('files') as $uploadedFile) {
                $path = $uploadedFile->store('order_final_delivery_files', 'public');
                $finalDelivery->files()->create([
                    'path' => $path,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'mime_type' => $uploadedFile->getClientMimeType(),
                    'size' => $uploadedFile->getSize(),
                ]);
            }

            $order->status = Order::STATUS_DELIVERED;
            if (!$order->delivered_at) {
                $order->delivered_at = now();
            }
            $order->save();

            $order->loadMissing(['user', 'service.freelancerProfile.user']);
            $order->user->notify(new OrderDeliveredToCustomerNotification($order));

            return $finalDelivery->load('files');
        });

        return response()->json([
            'message' => 'Entrega final enviada correctamente',
            'order_status' => Order::STATUS_DELIVERED,
            'final_delivery' => $finalDelivery,
        ], 201);
    }

    public function downloadFile(
        Request $request,
        Order $order,
        OrderFinalDeliveryFile $orderFinalDeliveryFile
    ) {
        if (!$this->canAccessOrder($request->user(), $order)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $finalDelivery = $order->finalDelivery()->first();
        if (!$finalDelivery) {
            return response()->json(['error' => 'La orden no tiene entrega final'], 404);
        }

        $file = $finalDelivery->files()->whereKey($orderFinalDeliveryFile->id)->first();
        if (!$file) {
            return response()->json(['error' => 'El archivo no pertenece a la entrega final de esa orden'], 422);
        }

        if (!Storage::disk('public')->exists($file->path)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        $response = response()->download(
            Storage::disk('public')->path($file->path),
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

        if ((int) $order->user_id === (int) $user->id) {
            return true;
        }

        return $this->isFreelancerOwner($user, $order);
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

