<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\EmailVerificationController;

// Rotte Pubbliche
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rotte Protette
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

// Rotta per la verifica dell'email (il link nell'email punterà qui)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed']) // Middleware di sicurezza per URL firmati
    ->name('verification.verify'); // Nome FONDAMENTALE usato da Laravel per creare il link

// Rotta per reinviare l'email di verifica
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware(['auth:sanctum', 'throttle:6,1']); // Limitiamo il reinvio a 1 volta al minuto

// Aggiungi questa nuova rotta per il reinvio pubblico
Route::post('/resend-verification-email', [EmailVerificationController::class, 'publicResend'])
    ->middleware('throttle:6,1'); // Limita a 6 richieste al minuto per IP per sicurezza