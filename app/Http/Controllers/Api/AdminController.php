<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $query = User::query()
            ->with(['role', 'freelancerProfile', 'company'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('names', 'ILIKE', "%{$search}%")
                    ->orWhere('last_names', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', (int) $request->role_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->paginate(15));
    }

    public function showUser(Request $request, User $user)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $user->load([
            'role',
            'freelancerProfile.skills.category',
            'company',
            'orders.service',
        ]);

        return response()->json($user);
    }

    public function updateUserStatus(Request $request, User $user)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        if ($request->user()->id === $user->id && $validated['is_active'] === false) {
            return response()->json([
                'error' => 'No puedes desactivar tu propio usuario administrador',
            ], 422);
        }

        $user->update(['is_active' => $validated['is_active']]);

        return response()->json([
            'message' => 'Estado de usuario actualizado correctamente',
            'user' => $user->fresh(['role']),
        ]);
    }

    public function services(Request $request)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $query = Service::query()
            ->with(['category', 'freelancerProfile.user'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->paginate(15));
    }

    public function updateServiceStatus(Request $request, Service $service)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $service->update(['is_active' => $validated['is_active']]);

        return response()->json([
            'message' => 'Estado de servicio actualizado correctamente',
            'service' => $service->fresh(['category', 'freelancerProfile.user']),
        ]);
    }

    public function stats(Request $request)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $ordersByStatus = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $topCategories = Category::query()
            ->withCount('services')
            ->orderByDesc('services_count')
            ->limit(5)
            ->get(['id', 'name']);

        return response()->json([
            'users' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
                'clientes' => User::where('role_id', 1)->count(),
                'freelancers' => User::where('role_id', 2)->count(),
                'empresas' => User::where('role_id', 3)->count(),
                'admins' => User::where('role_id', 4)->count(),
            ],
            'services' => [
                'total' => Service::count(),
                'active' => Service::where('is_active', true)->count(),
                'inactive' => Service::where('is_active', false)->count(),
            ],
            'orders' => [
                'total' => Order::count(),
                'by_status' => $ordersByStatus,
            ],
            'reviews' => [
                'total' => Review::count(),
                'avg_rating' => round((float) (Review::avg('rating') ?? 0), 2),
            ],
            'top_categories' => $topCategories,
        ]);
    }

    private function ensureAdmin(Request $request)
    {
        if ((int) $request->user()->role_id !== 4) {
            return response()->json(['error' => 'Solo administradores'], 403);
        }

        return null;
    }
}
