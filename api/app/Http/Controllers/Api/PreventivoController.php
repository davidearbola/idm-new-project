<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GeneraControproposte;
use App\Jobs\ProcessPreventivo;
use App\Models\PreventivoPaziente;
use App\Models\ContropropostaMedico;
use App\Models\PreventivoAccessCode;
use App\Notifications\InvioOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class PreventivoController extends Controller
{
    public function store(Request $request)
    {
        if ($request->hasFile('preventivo')) {
            $sizeInKb = round($request->file('preventivo')->getSize() / 1024, 2);
            Log::info("Nuovo preventivo ricevuto. Dimensione file: {$sizeInKb} KB");
        }

        $validatedData = $request->validate([
            'preventivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('preventivo');
        $maxSizeInBytes = 1024 * 1024; // 1MB
        $filePath = null;

        if (Str::startsWith($file->getMimeType(), 'image/') && $file->getSize() > $maxSizeInBytes) {
            $sourcePath = $file->getRealPath();
            list($originalWidth, $originalHeight, $imageType) = getimagesize($sourcePath);
            $sourceImage = null;
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
            }
            if ($sourceImage) {
                $maxWidth = 1200;
                $ratio = $originalWidth / $originalHeight;
                $newWidth = $maxWidth;
                $newHeight = $maxWidth / $ratio;
                $destImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
                $tempPath = tempnam(sys_get_temp_dir(), 'resized-') . '.' . $file->getClientOriginalExtension();
                switch ($imageType) {
                    case IMAGETYPE_JPEG:
                        imagejpeg($destImage, $tempPath, 85);
                        break;
                    case IMAGETYPE_PNG:
                        imagepng($destImage, $tempPath, 6);
                        break;
                }
                imagedestroy($sourceImage);
                imagedestroy($destImage);
                $fileName = 'preventivo_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                $finalDirectory = 'preventivi';
                Storage::disk('public')->putFileAs($finalDirectory, new File($tempPath), $fileName);
                $filePath = $finalDirectory . '/' . $fileName;
                unlink($tempPath);
            }
        }

        if (is_null($filePath)) {
            $fileName = 'preventivo_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('preventivi', $fileName, 'public');
        }

        $preventivo = PreventivoPaziente::create([
            'file_path'           => $filePath,
            'file_name_originale' => $file->getClientOriginalName(),
            'stato_elaborazione'  => 'caricato',
        ]);

        ProcessPreventivo::dispatch($preventivo);

        return response()->json([
            'success' => true,
            'message' => 'Preventivo caricato. Inizio elaborazione.',
            'preventivo_id' => $preventivo->id
        ], 201);
    }

    public function stato(PreventivoPaziente $preventivoPaziente)
    {
        $voci = null;
        if ($preventivoPaziente->stato_elaborazione === 'completato') {
            $voci = $preventivoPaziente->json_preventivo['voci_preventivo'] ?? [];
        }

        return response()->json([
            'stato_elaborazione' => $preventivoPaziente->stato_elaborazione,
            'voci_preventivo' => $voci,
            'messaggio_errore' => $preventivoPaziente->messaggio_errore,
        ]);
    }

    /**
     * Riceve le voci modificate, ricalcola il totale e salva l'intero oggetto JSON.
     */
    public function conferma(Request $request, PreventivoPaziente $preventivoPaziente)
    {
        if ($preventivoPaziente->stato_elaborazione !== 'completato') {
            return response()->json(['error' => 'Il preventivo non è ancora stato elaborato.'], 422);
        }

        $validated = $request->validate([
            'voci' => 'required|array',
            'voci.*.prestazione' => 'required|string',
            'voci.*.quantità' => 'required|integer|min:1',
            'voci.*.prezzo' => 'required|numeric|min:0',
        ]);

        // 1. Recupera le nuove voci validate
        $nuoveVoci = $validated['voci'];

        // 2. Ricalcola il totale basandosi sulle nuove voci
        $nuovoTotale = 0;
        foreach ($nuoveVoci as $voce) {
            $nuovoTotale += $voce['prezzo'];
        }

        // 3. Crea la nuova struttura JSON completa
        $datiAggiornati = [
            'voci_preventivo' => $nuoveVoci,
            'totale_preventivo' => $nuovoTotale
        ];

        // 4. Salva il nuovo oggetto JSON completo e cambia lo stato
        $preventivoPaziente->json_preventivo = $datiAggiornati;
        $preventivoPaziente->stato_elaborazione = 'attesa_dati_paziente';
        $preventivoPaziente->save();

        return response()->json([
            'success' => true,
            'message' => 'Voci del preventivo confermate. Inserisci i tuoi dati personali per proseguire.'
        ]);
    }

    /**
     * Salva i dati del paziente e avvia la ricerca delle controproposte
     */
    public function salvaDatiPaziente(Request $request, PreventivoPaziente $preventivoPaziente)
    {
        if ($preventivoPaziente->stato_elaborazione !== 'attesa_dati_paziente') {
            return response()->json(['error' => 'Il preventivo non è nello stato corretto.'], 422);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'cellulare' => 'nullable|string|min:9',
            'nome' => 'nullable|string|max:255',
            'cognome' => 'nullable|string|max:255',
            'indirizzo' => 'required|string|max:255',
            'citta' => 'required|string|max:255',
            'provincia' => 'required|string|size:2',
            'cap' => 'required|string|size:5',
        ]);

        // Geocoding dell'indirizzo (per ora usa coordinate di default, poi implementeremo servizio di geocoding)
        $lat = 45.4642000;
        $lng = 9.1900000;

        // GEOCODING CON GOOGLE MAPS API - DA ATTIVARE IN FUTURO
        // Costruisci l'indirizzo completo per la geocodifica
        $indirizzoCompleto = trim(implode(', ', [
            $validated['indirizzo'],
            $validated['citta'],
            $validated['provincia'],
            $validated['cap'],
            'Italia'
        ]));

        try {
            // Chiama l'API di Google Maps Geocoding
            $googleMapsApiKey = env('GOOGLE_MAPS_API_KEY');

            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $indirizzoCompleto,
                'key' => $googleMapsApiKey,
                'region' => 'it', // Privilegia risultati italiani
                'language' => 'it', // Risposta in italiano
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Verifica se Google ha trovato dei risultati
                if ($data['status'] === 'OK' && !empty($data['results'])) {
                    $location = $data['results'][0]['geometry']['location'];
                    $lat = $location['lat'];
                    $lng = $location['lng'];

                    Log::info('Geocoding completato con successo', [
                        'indirizzo' => $indirizzoCompleto,
                        'lat' => $lat,
                        'lng' => $lng,
                    ]);
                } else {
                    // Se Google non trova risultati, manteniamo le coordinate di default
                    Log::warning('Geocoding non ha prodotto risultati', [
                        'indirizzo' => $indirizzoCompleto,
                        'status' => $data['status'],
                    ]);
                }
            } else {
                Log::error('Errore nella chiamata API Google Maps Geocoding', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            // In caso di errore, manteniamo le coordinate di default
            Log::error('Eccezione durante il geocoding', [
                'error' => $e->getMessage(),
                'indirizzo' => $indirizzoCompleto,
            ]);
        }

        // Salva i dati del paziente
        $preventivoPaziente->update([
            'email_paziente' => $validated['email'],
            'cellulare_paziente' => $validated['cellulare'] ?? null,
            'nome_paziente' => $validated['nome'] ?? null,
            'cognome_paziente' => $validated['cognome'] ?? null,
            'indirizzo_paziente' => $validated['indirizzo'],
            'citta_paziente' => $validated['citta'],
            'provincia_paziente' => $validated['provincia'],
            'cap_paziente' => $validated['cap'],
            'lat_paziente' => $lat,
            'lng_paziente' => $lng,
            'stato_elaborazione' => 'ricerca_proposte',
        ]);

        // Avvia il job per generare le controproposte
        GeneraControproposte::dispatch($preventivoPaziente);

        return response()->json([
            'success' => true,
            'message' => 'Dati salvati. Stiamo cercando le migliori proposte per te.'
        ]);
    }

    /**
     * Controlla se sono state generate delle proposte per un dato preventivo.
     */
    public function proposteStato(PreventivoPaziente $preventivoPaziente)
    {
        $proposte = ContropropostaMedico::where('preventivo_paziente_id', $preventivoPaziente->id)
            ->with(['medico.anagraficaMedico'])
            ->get();

        return response()->json([
            'stato_elaborazione' => $preventivoPaziente->stato_elaborazione,
            'proposte_pronte' => $proposte,
            'voci_preventivo' => $preventivoPaziente->json_preventivo['voci_preventivo'] ?? [],
        ]);
    }

    /**
     * Recupera le proposte tramite email del paziente.
     */
    public function recuperaProposte(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        // Trova tutti i preventivi con questa email
        $preventivi = PreventivoPaziente::where('email_paziente', $validated['email'])
            ->where('stato_elaborazione', 'proposte_pronte')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($preventivi->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nessuna proposta trovata per questa email.'
            ], 404);
        }

        // Recupera tutte le proposte per questi preventivi
        $preventiviIds = $preventivi->pluck('id');
        $proposte = ContropropostaMedico::whereIn('preventivo_paziente_id', $preventiviIds)
            ->with(['medico.anagraficaMedico', 'preventivoPaziente'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'preventivi' => $preventivi,
            'proposte' => $proposte,
        ]);
    }

    /**
     * Richiedi una chiamata dall'operatore per una proposta specifica.
     */
    public function richiediChiamata(Request $request)
    {
        $validated = $request->validate([
            'proposta_id' => 'required|integer|exists:controproposte_medici,id',
            'nome' => 'required|string|max:255',
            'cognome' => 'required|string|max:255',
            'cellulare' => 'required|string|min:9|max:20',
        ]);

        // Recupera la proposta con i dati del preventivo e del medico
        $proposta = ContropropostaMedico::with(['preventivoPaziente', 'medico.anagraficaMedico'])
            ->findOrFail($validated['proposta_id']);

        $preventivo = $proposta->preventivoPaziente;

        // Prepara i dati per la chiamata API esterna
        $nominativo = trim($validated['nome'] . ' ' . $validated['cognome']);
        $telefono = $validated['cellulare'];

        // Dati opzionali dal preventivo
        $indirizzo = $preventivo->indirizzo_paziente ?? '';
        $citta = $preventivo->citta_paziente ?? '';
        $cap = $preventivo->cap_paziente ?? '';
        $provincia = $preventivo->provincia_paziente ?? '';

        // Prepara data e ora correnti nel formato richiesto
        // Proviamo formato americano MM/dd/yyyy invece di dd/MM/yyyy
        $now = now();
        $recallDate = $now->format('m/d/Y'); // es: 10/13/2025 (formato americano)
        $recallTime = $now->format('H:i');   // es: 15:37
        $recallTimeTo = $now->copy()->addMinutes(15)->format('H:i'); // +15 minuti

        // Costruisci i parametri per la chiamata API
        $params = [
            'ServicePIN' => '00000001',
            'Nominativo' => $nominativo,
            'Telefono' => $telefono,
            'Indirizzo' => $indirizzo,
            'Citta' => $citta,
            'Cap' => $cap,
            'Provincia' => $provincia,
            'Prefisso' => '', // Non abbiamo questo dato separato
            'ExtraFields' => 'Fonte=RichiestaCallbackWeb',
            'RecallDate' => $recallDate,
            'RecallTime' => $recallTime,
            'RecallTimeTo' => $recallTimeTo,
        ];

        try {
            // Effettua la chiamata POST all'API esterna
            $response = Http::asForm()->post('https://novarod.telmar.cloud/T2KWEBAPI/WebServices.asmx/AddLeadEX', $params);

            // Log della risposta per debugging
            Log::info('Richiesta chiamata operatore - Risposta API Telmar', [
                'proposta_id' => $validated['proposta_id'],
                'nominativo' => $nominativo,
                'telefono' => $telefono,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            // Verifica se la chiamata è andata a buon fine
            if ($response->successful()) {
                $proposta->update(["stato" => "richiesta_chiamata"]);
                $preventivo->update([
                    "cellulare_paziente" => $telefono,
                    "nome_paziente" => $validated['nome'],
                    "cognome_paziente" => $validated['cognome']
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Richiesta di chiamata inviata con successo! Ti contatteremo a breve.',
                ]);
            } else {
                Log::error('Errore nella chiamata API Telmar', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Si è verificato un errore durante l\'invio della richiesta. Riprova più tardi.',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Eccezione durante la chiamata API Telmar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Si è verificato un errore imprevisto. Riprova più tardi.',
            ], 500);
        }
    }

    /**
     * Accesso alle proposte tramite access_token (link da email)
     */
    public function accessoConToken(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        // Trova il preventivo con questo access_token
        $preventivo = PreventivoPaziente::where('access_token', $validated['token'])
            ->where('stato_elaborazione', 'proposte_pronte')
            ->first();

        if (!$preventivo) {
            return response()->json([
                'success' => false,
                'message' => 'Link non valido o proposte non ancora disponibili.'
            ], 404);
        }

        // Recupera le proposte per questo preventivo
        $proposte = ContropropostaMedico::where('preventivo_paziente_id', $preventivo->id)
            ->with(['medico.anagraficaMedico'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'preventivo' => $preventivo,
            'proposte' => $proposte,
        ]);
    }

    /**
     * Richiedi un codice OTP per accedere alle proposte
     */
    public function richiediOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = $validated['email'];

        // Verifica se esistono preventivi con questa email
        $preventiviCount = PreventivoPaziente::where('email_paziente', $email)
            ->where('stato_elaborazione', 'proposte_pronte')
            ->count();

        if ($preventiviCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nessuna proposta trovata per questa email.'
            ], 404);
        }

        // Verifica se l'email è già bloccata
        $existingCode = PreventivoAccessCode::forEmail($email)
            ->latest()
            ->first();

        if ($existingCode && $existingCode->isBlocked()) {
            $minutesLeft = now()->diffInMinutes($existingCode->blocked_until);
            return response()->json([
                'success' => false,
                'message' => "Troppi tentativi falliti. Riprova tra {$minutesLeft} minuti.",
                'blocked_until' => $existingCode->blocked_until->toIso8601String(),
            ], 429);
        }

        // Elimina eventuali OTP precedenti per questa email
        PreventivoAccessCode::where('email', $email)->delete();

        // Genera nuovo OTP
        $otpCode = PreventivoAccessCode::generateOtp();
        $expiresAt = now()->addMinutes(10);

        // Salva nel database
        $accessCode = PreventivoAccessCode::create([
            'email' => $email,
            'otp_code' => $otpCode,
            'expires_at' => $expiresAt,
            'attempts' => 0,
        ]);

        // Invia email con OTP
        try {
            Notification::route('mail', $email)
                ->notify(new InvioOtpNotification($otpCode, 10));

            return response()->json([
                'success' => true,
                'message' => 'Codice di verifica inviato via email.',
                'expires_in_minutes' => 10,
            ]);
        } catch (\Exception $e) {
            Log::error('Errore invio OTP', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore durante l\'invio dell\'email. Riprova più tardi.',
            ], 500);
        }
    }

    /**
     * Verifica il codice OTP e restituisce l'accesso alle proposte
     */
    public function verificaOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required|string|size:6',
        ]);

        $email = $validated['email'];
        $otpCode = $validated['otp_code'];

        // Trova il codice OTP più recente per questa email
        $accessCode = PreventivoAccessCode::forEmail($email)
            ->latest()
            ->first();

        if (!$accessCode) {
            return response()->json([
                'success' => false,
                'message' => 'Codice non valido o scaduto. Richiedi un nuovo codice.',
            ], 404);
        }

        // Verifica se è bloccato
        if ($accessCode->isBlocked()) {
            $minutesLeft = now()->diffInMinutes($accessCode->blocked_until);
            return response()->json([
                'success' => false,
                'message' => "Troppi tentativi falliti. Riprova tra {$minutesLeft} minuti.",
                'blocked_until' => $accessCode->blocked_until->toIso8601String(),
            ], 429);
        }

        // Verifica se è scaduto
        if ($accessCode->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Il codice è scaduto. Richiedi un nuovo codice.',
            ], 410);
        }

        // Verifica il codice OTP
        if ($accessCode->otp_code !== $otpCode) {
            $accessCode->incrementAttempts();

            $remainingAttempts = 5 - $accessCode->attempts;

            if ($remainingAttempts > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Codice non corretto. Ti rimangono {$remainingAttempts} tentativi.",
                    'remaining_attempts' => $remainingAttempts,
                ], 400);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Troppi tentativi falliti. Account bloccato per 1 ora.',
                    'blocked_until' => $accessCode->blocked_until->toIso8601String(),
                ], 429);
            }
        }

        // OTP corretto! Elimina il codice usato
        $accessCode->delete();

        // Recupera tutti i preventivi con questa email
        $preventivi = PreventivoPaziente::where('email_paziente', $email)
            ->where('stato_elaborazione', 'proposte_pronte')
            ->orderBy('created_at', 'desc')
            ->get();

        // Recupera tutte le proposte per questi preventivi
        $preventiviIds = $preventivi->pluck('id');
        $proposte = ContropropostaMedico::whereIn('preventivo_paziente_id', $preventiviIds)
            ->with(['medico.anagraficaMedico', 'preventivoPaziente'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Accesso consentito.',
            'preventivi' => $preventivi,
            'proposte' => $proposte,
        ]);
    }
}