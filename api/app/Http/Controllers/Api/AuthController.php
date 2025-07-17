<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnagraficaMedico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'role' => 'paziente',
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Registrazione effettuata con successo. Per favore controlla la tua email per verificare il tuo account!'
        ], 201);
    }

    public function registerMedico(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'ragione_sociale' => ['required', 'string', 'max:255'],
            'p_iva' => ['required', 'string', 'max:20', 'unique:' . AnagraficaMedico::class],
            'cellulare' => ['required', 'string', 'max:20'],
            'indirizzo' => ['required', 'string', 'max:255'],
            'citta' => ['required', 'string', 'max:255'],
            'cap' => ['required', 'string', 'max:10'],
            'provincia' => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'role' => 'medico',
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // --- BLOCCO AGGIUNTO ---
        // Geocodifica l'indirizzo del medico
        // TODO: Riattivare la chiamata all'API di Google quando sarà disponibile!
        /*
        $fullAddress = "{$request->indirizzo}, {$request->cap} {$request->citta}, {$request->provincia}";
        $apiKey = env('Maps_API_KEY');
        $lat = null;
        $lng = null;
    
        $response = Http::get("https://maps.googleapis.com/maps/api/geocode/json", [
            'address' => $fullAddress,
            'key' => $apiKey,
        ]);
    
        if ($response->successful() && $response->json('status') === 'OK') {
            $location = $response->json('results.0.geometry.location');
            $lat = $location['lat'];
            $lng = $location['lng'];
        }
        */

        // WORKAROUND TEMPORANEO: Usiamo coordinate fisse (es. centro di Roma)
        $lat = 45.4642;
        $lng = 9.1900;
        // --- FINE BLOCCO AGGIUNTO ---

        $user->anagraficaMedico()->create([
            'ragione_sociale' => $request->ragione_sociale,
            'p_iva' => $request->p_iva,
            'cellulare' => $request->cellulare,
            'indirizzo' => $request->indirizzo,
            'citta' => $request->citta,
            'cap' => $request->cap,
            'provincia' => $request->provincia,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Registrazione effettuata con successo. Per favore controlla la tua email per verificare il tuo account!'
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Per favore, verifica il tuo indirizzo email prima di accedere.'], 403);
        }

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return response()->json([
                'success' => false,
                'message' => trans('auth.failed')
            ], 401);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'user' => Auth::user()
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout effettuato con successo.']);
    }

    public function user(Request $request)
    {
        return $request->user();
    }
}
