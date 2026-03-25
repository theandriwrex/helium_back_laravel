<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Notifications\OrderAcceptedByFreelancerNotification;
use App\Notifications\OrderCreatedForFreelancerNotification;
use App\Notifications\OrderDeliveredToCustomerNotification;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::query()
            ->with(['user', 'service.category', 'service.freelancerProfile.user'])
            ->withExists('review');

        if ($user->role_id == 4) {
            return response()->json($query->latest()->paginate(10));
        }

        if ($user->role_id == 2) {
            $query->whereHas('service.freelancerProfile', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->latest()->paginate(10));
    }

    public function show(Request $request, Order $order)
    {
        if (!$this->canViewOrder($request->user(), $order)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $order->load([
            'user:id,names,last_names,email,photo',
            'service.category',
            'service.freelancerProfile.user:id,names,last_names,email,photo',
        ]);
        $order->loadExists('review');

        return response()->json($order);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role_id, [1, 3], true)) {
            return response()->json(['error' => 'Solo clientes o empresas pueden crear pedidos'], 403);
        }

        $validated = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'requirements' => 'nullable|string',
            'pse_reference' => 'nullable|string|max:255',
        ]);

        $service = Service::with('freelancerProfile')->findOrFail($validated['service_id']);

        if (!$service->is_active) {
            return response()->json(['error' => 'El servicio no está disponible'], 422);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'amount' => $service->price,
            'pse_reference' => $validated['pse_reference'] ?? null,
            'requirements' => $validated['requirements'] ?? null,
            'status' => Order::STATUS_PENDING,
        ]);

        $order->load(['user', 'service.category', 'service.freelancerProfile.user']);

        $freelancerUser = optional($order->service->freelancerProfile)->user;
        if ($freelancerUser) {
            $freelancerUser->notify(new OrderCreatedForFreelancerNotification($order));
        }

        return response()->json($order, 201);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', Order::ALLOWED_STATUSES),
        ]);

        $newStatus = $validated['status'];
        $currentStatus = $order->status;
        $isClientOwner = $order->user_id === $user->id;
        $isFreelancerOwner = optional($order->service->freelancerProfile)->user_id === $user->id;
        $isAdmin = $user->role_id == 4;

        if (!$isClientOwner && !$isFreelancerOwner && !$isAdmin) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if (!$this->isValidTransition($currentStatus, $newStatus, $isClientOwner, $isFreelancerOwner, $isAdmin)) {
            return response()->json([
                'error' => 'Transición de estado no permitida',
                'current_status' => $currentStatus,
                'requested_status' => $newStatus,
            ], 422);
        }

        $order->status = $newStatus;

        if ($newStatus === Order::STATUS_IN_PROGRESS && !$order->started_at) {
            $order->started_at = now();
        }

        if ($newStatus === Order::STATUS_DELIVERED && !$order->delivered_at) {
            $order->delivered_at = now();
        }

        if ($newStatus === Order::STATUS_COMPLETED && !$order->completed_at) {
            $order->completed_at = now();
        }

        if ($newStatus === Order::STATUS_CANCELLED && !$order->cancelled_at) {
            $order->cancelled_at = now();
        }

        $order->save();

        if ($newStatus === Order::STATUS_IN_PROGRESS) {
            $order->loadMissing(['user', 'service.freelancerProfile.user']);
            $order->user->notify(new OrderAcceptedByFreelancerNotification($order));
        }

        if ($newStatus === Order::STATUS_DELIVERED) {
            $order->loadMissing(['user', 'service.freelancerProfile.user']);
            $order->user->notify(new OrderDeliveredToCustomerNotification($order));
        }

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'order' => $order->fresh(['service.category', 'service.freelancerProfile.user']),
        ]);
    }

    private function canViewOrder($user, Order $order): bool
    {
        if ($user->role_id == 4) {
            return true;
        }

        if ($order->user_id === $user->id) {
            return true;
        }

        return optional($order->service->freelancerProfile)->user_id === $user->id;
    }

    private function isValidTransition(
        string $currentStatus,
        string $newStatus,
        bool $isClientOwner,
        bool $isFreelancerOwner,
        bool $isAdmin
    ): bool {
        if ($isAdmin) {
            return true;
        }

        if ($newStatus === Order::STATUS_CANCELLED) {
            return ($isClientOwner || $isFreelancerOwner)
                && !in_array($currentStatus, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED], true);
        }

        if ($isFreelancerOwner) {
            return ($currentStatus === Order::STATUS_PENDING && $newStatus === Order::STATUS_IN_PROGRESS)
                || ($currentStatus === Order::STATUS_IN_PROGRESS && $newStatus === Order::STATUS_DELIVERED);
        }

        if ($isClientOwner) {
            return $currentStatus === Order::STATUS_DELIVERED && $newStatus === Order::STATUS_COMPLETED;
        }

        return false;
    }
}
