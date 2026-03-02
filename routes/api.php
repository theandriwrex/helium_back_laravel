<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
});
