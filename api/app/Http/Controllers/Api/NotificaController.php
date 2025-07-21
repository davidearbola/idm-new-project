<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificaController extends Controller
{
    /**
     * Restituisce le notifiche non lette per l'utente autenticato.
     */
    public function index()
    {
        $user = Auth::user();

        $notificheNonLette = $user->notifiche()->whereNull('letta_at')->get();

        return response()->json($notificheNonLette);
    }
}
