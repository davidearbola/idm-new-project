<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ImpostazioniUtenteController;
use App\Http\Controllers\Api\ListinoController;
use App\Http\Controllers\Api\PreventivoController;
use App\Http\Controllers\Api\ProfiloMedicoController;
use App\Http\Controllers\Api\NotificaController;
use App\Http\Controllers\Api\PropostaController;

// Rotte Pubbliche
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register-medico', [AuthController::class, 'registerMedico']);

// Rotte Protette
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/preventivi', [PreventivoController::class, 'store']);
    Route::get('/notifiche', [NotificaController::class, 'index']);
    Route::prefix('impostazioni')->group(function () {
        Route::post('/anagrafica', [ImpostazioniUtenteController::class, 'updateAnagrafica']);
        Route::put('/email', [ImpostazioniUtenteController::class, 'updateEmail']);
        Route::put('/password', [ImpostazioniUtenteController::class, 'updatePassword']);
    });
    Route::prefix('listino')->group(function () {
        Route::get('/', [ListinoController::class, 'index']);
        Route::post('/master', [ListinoController::class, 'updateMasterItem']);
        Route::post('/custom', [ListinoController::class, 'storeCustomItem']);
        Route::put('/custom/{item}', [ListinoController::class, 'updateCustomItem']);
        Route::delete('/custom/{item}', [ListinoController::class, 'destroyCustomItem']);
    });
    Route::prefix('profilo-medico')->group(function () {
        Route::get('/', [ProfiloMedicoController::class, 'index']);
        Route::post('/descrizione', [ProfiloMedicoController::class, 'updateDescrizione']);
        Route::post('/foto-studio', [ProfiloMedicoController::class, 'uploadFotoStudio']);
        Route::delete('/foto-studio/{foto}', [ProfiloMedicoController::class, 'destroyFotoStudio']);
        Route::post('/staff', [ProfiloMedicoController::class, 'storeStaff']);
        Route::put('/staff/{staff}', [ProfiloMedicoController::class, 'updateStaff']);
        Route::delete('/staff/{staff}', [ProfiloMedicoController::class, 'destroyStaff']);
    });
    Route::prefix('proposte')->group(function () {
        Route::get('/', [PropostaController::class, 'index']);
        Route::post('/mark-as-read', [PropostaController::class, 'markAsRead']);
        Route::post('/{proposta}/accetta', [PropostaController::class, 'accetta']);
        Route::post('/{proposta}/rifiuta', [PropostaController::class, 'rifiuta']);
    });
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