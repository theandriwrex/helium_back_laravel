<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ProfileController;
require __DIR__.'/auth.php';

Route::middleware('auth:sanctum')->get('/profile', [ProfileController::class, 'me']);
Route::middleware('auth:sanctum')->patch('/profile', [ProfileController::class, 'update']);
Route::middleware('auth:sanctum')->patch('/profile/skills', [ProfileController::class, 'updateSkills']);
Route::get('/skills', [ProfileController::class, 'showSkills']);
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/top', [ServiceController::class, 'top']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::get('/services/{service}/reviews', [ReviewController::class, 'indexByService']);
Route::get('/metrics/active-counts', [MetricsController::class, 'activeCounts']);
Route::get('/freelancers/top', [ProfileController::class, 'topFreelancers']);
Route::get('/freelancers', [ProfileController::class, 'listFreelancers']);
Route::get('/freelancers/{freelancerProfile}', [ProfileController::class, 'showFreelancer']);

Route::middleware('auth:sanctum')->group(function () {


    // estas rutas son para que los freelancers puedan crear, actualizar y eliminar sus servicios, es decir,
    //  los servicios que ofrecen en la plataforma,
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
    Route::get('/personaServices', [ServiceController::class, 'myServices']);


    // esto es para poder crear ordenes, verlas, y actualizar su estado, es decir cuando un cliente contrata un servicio,
    //  se crea una orden, y el freelancer puede actualizar el estado de la orden a medida que avanza en el trabajo, 
    // por ejemplo: "en progreso", "completada", etc.
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/review', [ReviewController::class, 'store']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus']);

        Route::get('/services', [AdminController::class, 'services']);
        Route::get('/services/worst-rated', [AdminController::class, 'worstRatedServices']);
        Route::get('/freelancers/worst-rated', [AdminController::class, 'worstRatedFreelancers']);
        Route::patch('/services/{service}/status', [AdminController::class, 'updateServiceStatus']);

        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/dashboard-summary', [AdminController::class, 'dashboardSummary']);
        Route::get('/reviews/negative', [AdminController::class, 'negativeReviews']);
    });
});
