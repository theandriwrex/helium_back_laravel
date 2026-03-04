<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ProfileController;
require __DIR__.'/auth.php';

Route::middleware('auth:sanctum')->get('/profile', [ProfileController::class, 'me']);
Route::middleware('auth:sanctum')->patch('/profile', [ProfileController::class, 'update']);
Route::middleware('auth:sanctum')->patch('/profile/skills', [ProfileController::class, 'updateSkills']);
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::get('/freelancers/{freelancerProfile}', [ProfileController::class, 'showFreelancer']);

Route::middleware('auth:sanctum')->group(function () {


    // estas rutas son para que los freelancers puedan crear, actualizar y eliminar sus servicios, es decir,
    //  los servicios que ofrecen en la plataforma,
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

    // esto es para poder crear ordenes, verlas, y actualizar su estado, es decir cuando un cliente contrata un servicio,
    //  se crea una orden, y el freelancer puede actualizar el estado de la orden a medida que avanza en el trabajo, 
    // por ejemplo: "en progreso", "completada", etc.
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});
