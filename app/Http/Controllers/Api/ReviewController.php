<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado para reseñar esta orden'], 403);
        }

        if ($order->status !== Order::STATUS_COMPLETED) {
            return response()->json([
                'error' => 'Solo puedes reseñar ordenes completadas',
            ], 422);
        }

        if ($order->review()->exists()) {
            return response()->json([
                'error' => 'Esta orden ya tiene una reseña',
            ], 422);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review = Review::create([
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Reseña creada correctamente',
            'review' => $review->load('order.service'),
        ], 201);
    }

    public function indexByService(Service $service)
    {
        $reviews = $service->reviews()
            ->with(['order.user:id,names,last_names,photo'])
            ->latest()
            ->paginate(10);

        $ratingStats = $service->reviews()
            ->selectRaw('COUNT(*) as total_reviews, COALESCE(AVG(rating), 0) as avg_rating')
            ->first();

        return response()->json([
            'service_id' => $service->id,
            'avg_rating' => round((float) $ratingStats->avg_rating, 2),
            'reviews_count' => (int) $ratingStats->total_reviews,
            'reviews' => $reviews,
        ]);
    }
}
