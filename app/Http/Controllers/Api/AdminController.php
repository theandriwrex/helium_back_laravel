<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FreelancerProfile;
use App\Models\Order;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    private const ADMIN_CACHE_SECONDS = 60;

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

        $user->is_active = $validated['is_active'];
        $user->save();
        $user->refresh();

        return response()->json([
            'message' => 'Estado de usuario actualizado correctamente',
            'user' => $user->load('role'),
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

        if ($request->filled('status')) {
            $status = strtolower($request->string('status')->toString());
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        } elseif ($request->filled('is_active')) {
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
            'deactivation_reason' => 'nullable|string|max:2000',
        ]);

        $payload = [
            'is_active' => $validated['is_active'],
            'deactivation_reason' => null,
        ];

        if ($validated['is_active'] === false) {
            $reason = trim((string) ($validated['deactivation_reason'] ?? ''));
            if ($reason === '') {
                return response()->json([
                    'error' => 'Debes enviar deactivation_reason al desactivar un servicio',
                ], 422);
            }
            $payload['deactivation_reason'] = $reason;
        }

        $service->update($payload);

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

        $range = $request->get('range', 'all');
        $cacheKey = "admin:stats:{$range}";

        $stats = Cache::remember($cacheKey, self::ADMIN_CACHE_SECONDS, function () use ($range) {
            $ordersMetrics = $this->buildOrdersMetrics($range);

            $topCategories = Category::query()
                ->withCount('services')
                ->orderByDesc('services_count')
                ->limit(5)
                ->get(['id', 'name']);

            return [
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
                    'total' => $ordersMetrics['total_orders'],
                    'by_status' => $ordersMetrics['orders_by_status'],
                ],
                'kpis' => [
                    'total_orders' => $ordersMetrics['total_orders'],
                    'completion_rate' => $ordersMetrics['completion_rate'],
                    'cancellation_rate' => $ordersMetrics['cancellation_rate'],
                    'avg_ticket' => $ordersMetrics['avg_ticket'],
                ],
                'reviews' => [
                    'total' => Review::count(),
                    'avg_rating' => round((float) (Review::avg('rating') ?? 0), 2),
                ],
                'top_categories' => $topCategories,
                'range' => $range,
            ];
        });

        return response()->json($stats);
    }

    public function negativeReviews(Request $request)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $days = (int) $request->get('days', 30);
        $maxRating = (int) $request->get('max_rating', 2);
        $limit = (int) $request->get('limit', 20);

        if ($days < 1) {
            $days = 1;
        }
        if ($days > 365) {
            $days = 365;
        }

        if ($maxRating < 1) {
            $maxRating = 1;
        }
        if ($maxRating > 5) {
            $maxRating = 5;
        }

        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $reviews = $this->queryNegativeReviews($days, $maxRating, $limit);

        return response()->json([
            'filters' => [
                'days' => $days,
                'max_rating' => $maxRating,
                'limit' => $limit,
            ],
            'total' => $reviews->count(),
            'reviews' => $reviews,
        ]);
    }

    public function worstRatedServices(Request $request)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $limit = (int) $request->get('limit', 4);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $services = $this->queryWorstRatedServices($limit);

        return response()->json([
            'limit' => $limit,
            'total' => $services->count(),
            'services' => $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'category' => $service->category?->name,
                    'avg_rating' => round((float) ($service->reviews_avg_rating ?? 0), 2),
                    'reviews_count' => (int) ($service->reviews_count ?? 0),
                    'freelancer' => [
                        'profile_id' => $service->freelancerProfile?->id,
                        'user_id' => $service->freelancerProfile?->user?->id,
                        'name' => trim(($service->freelancerProfile?->user?->names ?? '') . ' ' . ($service->freelancerProfile?->user?->last_names ?? '')),
                        'email' => $service->freelancerProfile?->user?->email,
                    ],
                ];
            }),
        ]);
    }

    public function worstRatedFreelancers(Request $request)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $limit = (int) $request->get('limit', 5);
        $minReviews = (int) $request->get('min_reviews', 1);

        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        if ($minReviews < 1) {
            $minReviews = 1;
        }
        if ($minReviews > 1000) {
            $minReviews = 1000;
        }

        $freelancers = $this->queryWorstRatedFreelancers($limit, $minReviews);

        return response()->json([
            'limit' => $limit,
            'min_reviews' => $minReviews,
            'total' => $freelancers->count(),
            'freelancers' => $freelancers,
        ]);
    }

    public function dashboardSummary(Request $request)
    {
        if ($response = $this->ensureAdmin($request)) {
            return $response;
        }

        $range = $request->get('range', 'all');
        $worstLimit = (int) $request->get('worst_limit', 4);
        $negativeDays = (int) $request->get('negative_days', 30);
        $negativeMaxRating = (int) $request->get('negative_max_rating', 2);
        $negativeLimit = (int) $request->get('negative_limit', 10);

        $cacheKey = sprintf(
            'admin:dashboard-summary:%s:%d:%d:%d:%d',
            $range,
            $worstLimit,
            $negativeDays,
            $negativeMaxRating,
            $negativeLimit
        );

        $summary = Cache::remember($cacheKey, self::ADMIN_CACHE_SECONDS, function () use ($range, $worstLimit, $negativeDays, $negativeMaxRating, $negativeLimit) {
            $ordersMetrics = $this->buildOrdersMetrics($range);
            $worstServices = $this->queryWorstRatedServices($worstLimit)->map(function ($service) {
                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'category' => $service->category?->name,
                    'avg_rating' => round((float) ($service->reviews_avg_rating ?? 0), 2),
                    'reviews_count' => (int) ($service->reviews_count ?? 0),
                    'freelancer' => [
                        'profile_id' => $service->freelancerProfile?->id,
                        'user_id' => $service->freelancerProfile?->user?->id,
                        'name' => trim(($service->freelancerProfile?->user?->names ?? '') . ' ' . ($service->freelancerProfile?->user?->last_names ?? '')),
                        'email' => $service->freelancerProfile?->user?->email,
                    ],
                ];
            });

            $negativeReviews = $this->queryNegativeReviews($negativeDays, $negativeMaxRating, $negativeLimit);

            return [
                'range' => $range,
                'kpis' => [
                    'total_orders' => $ordersMetrics['total_orders'],
                    'completion_rate' => $ordersMetrics['completion_rate'],
                    'cancellation_rate' => $ordersMetrics['cancellation_rate'],
                    'avg_ticket' => $ordersMetrics['avg_ticket'],
                ],
                'orders_by_status' => $ordersMetrics['orders_by_status'],
                'worst_rated_services' => [
                    'limit' => $worstLimit,
                    'total' => $worstServices->count(),
                    'services' => $worstServices,
                ],
                'negative_reviews_recent' => [
                    'filters' => [
                        'days' => $negativeDays,
                        'max_rating' => $negativeMaxRating,
                        'limit' => $negativeLimit,
                    ],
                    'total' => $negativeReviews->count(),
                    'reviews' => $negativeReviews,
                ],
            ];
        });

        return response()->json($summary);
    }

    private function buildOrdersMetrics(string $range): array
    {
        $ordersQuery = Order::query();

        if ($range === 'today') {
            $ordersQuery->whereDate('created_at', now()->toDateString());
        } elseif ($range === '7d') {
            $ordersQuery->where('created_at', '>=', now()->subDays(7)->startOfDay());
        } elseif ($range === '30d') {
            $ordersQuery->where('created_at', '>=', now()->subDays(30)->startOfDay());
        }

        $metrics = (clone $ordersQuery)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count", [Order::STATUS_PENDING])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_count", [Order::STATUS_IN_PROGRESS])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered_count", [Order::STATUS_DELIVERED])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count", [Order::STATUS_COMPLETED])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_count", [Order::STATUS_CANCELLED])
            ->selectRaw('COALESCE(AVG(amount), 0) as avg_ticket')
            ->first();

        $totalOrders = (int) ($metrics->total_orders ?? 0);
        $completedCount = (int) ($metrics->completed_count ?? 0);
        $cancelledCount = (int) ($metrics->cancelled_count ?? 0);

        return [
            'total_orders' => $totalOrders,
            'orders_by_status' => [
                Order::STATUS_PENDING => (int) ($metrics->pending_count ?? 0),
                Order::STATUS_IN_PROGRESS => (int) ($metrics->in_progress_count ?? 0),
                Order::STATUS_DELIVERED => (int) ($metrics->delivered_count ?? 0),
                Order::STATUS_COMPLETED => $completedCount,
                Order::STATUS_CANCELLED => $cancelledCount,
            ],
            'completion_rate' => $totalOrders > 0
                ? round(($completedCount / $totalOrders) * 100, 2)
                : 0.0,
            'cancellation_rate' => $totalOrders > 0
                ? round(($cancelledCount / $totalOrders) * 100, 2)
                : 0.0,
            'avg_ticket' => round((float) ($metrics->avg_ticket ?? 0), 2),
        ];
    }

    private function queryWorstRatedServices(int $limit)
    {
        return Service::query()
            ->with(['category:id,name', 'freelancerProfile.user:id,names,last_names,email'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('is_active', true)
            ->whereHas('reviews')
            ->orderBy('reviews_avg_rating', 'asc')
            ->orderByDesc('reviews_count')
            ->limit($limit)
            ->get();
    }

    private function queryNegativeReviews(int $days, int $maxRating, int $limit)
    {
        return Review::query()
            ->with([
                'order:id,user_id,service_id,status,created_at',
                'order.user:id,names,last_names,email',
                'order.service:id,title,freelancer_id,category_id',
                'order.service.freelancerProfile:id,user_id',
                'order.service.freelancerProfile.user:id,names,last_names,email',
            ])
            ->where('rating', '<=', $maxRating)
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function queryWorstRatedFreelancers(int $limit, int $minReviews)
    {
        $profiles = FreelancerProfile::query()
            ->with('user:id,names,last_names,email,photo,role_id,is_active')
            ->whereHas('user', function ($query) {
                $query->where('role_id', 2)
                    ->where('is_active', true);
            })
            ->with(['services' => function ($query) {
                $query->where('is_active', true)
                    ->select('id', 'freelancer_id', 'title')
                    ->withAvg('reviews', 'rating')
                    ->withCount('reviews');
            }])
            ->get();

        return $profiles
            ->map(function ($profile) {
                $ratedServices = $profile->services->where('reviews_count', '>', 0);
                $avgServiceRating = $ratedServices->isNotEmpty()
                    ? round((float) $ratedServices->avg(function ($service) {
                        return (float) $service->reviews_avg_rating;
                    }), 2)
                    : 0.0;

                return [
                    'freelancer_profile_id' => $profile->id,
                    'user' => $profile->user,
                    'avg_service_rating' => $avgServiceRating,
                    'services_count' => $profile->services->count(),
                    'reviews_count' => (int) $profile->services->sum('reviews_count'),
                ];
            })
            ->filter(function ($item) use ($minReviews) {
                return $item['reviews_count'] >= $minReviews;
            })
            ->sort(function ($a, $b) {
                if ($a['avg_service_rating'] == $b['avg_service_rating']) {
                    return $b['reviews_count'] <=> $a['reviews_count'];
                }

                return $a['avg_service_rating'] <=> $b['avg_service_rating'];
            })
            ->values()
            ->take($limit);
    }

    private function ensureAdmin(Request $request)
    {
        if ((int) $request->user()->role_id !== 4) {
            return response()->json(['error' => 'Solo administradores'], 403);
        }

        return null;
    }
}
