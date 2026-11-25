<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Pública, pero limitada a 5 intentos por minuto (ver AppServiceProvider).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Todo lo demás exige un token Bearer válido y no vencido.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::patch('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    // Práctica 3.1: notificación de producto dañado (asíncrona con RabbitMQ y versión síncrona)
    Route::post('/notificaciones', [NotificacionController::class, 'store']);
    Route::post('/notificaciones/sincrona', [NotificacionController::class, 'sincrona']);
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::get('/notificaciones/{messageId}', [NotificacionController::class, 'show']);

    // Práctica 3.2: eventos de producto dañado enrutados por contenido
    Route::post('/eventos', [EventoController::class, 'store']);
    Route::get('/eventos', [EventoController::class, 'index']);
    Route::get('/eventos/{id}', [EventoController::class, 'show']);
});
