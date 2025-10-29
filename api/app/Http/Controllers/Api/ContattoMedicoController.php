<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContattoMedicoController extends Controller
{
    /**
     * Salva la richiesta di contatto da parte di un medico
     * e la invia a Telmar tramite API.
     */
    public function richiediContatto(Request $request)
    {
        $validated = $request->validate([
            'nominativo' => 'required|string|max:255',
            'nome_studio' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'required|string|min:9|max:20',
        ]);

        // Prepara i dati per la chiamata API esterna
        $nominativo = $validated['nominativo'];
        $telefono = $validated['telefono'];

        // Prepara data e ora correnti nel formato richiesto (formato americano)
        $now = now();
        $recallDate = $now->format('m/d/Y'); // es: 10/13/2025 (formato americano)
        $recallTime = $now->format('H:i');   // es: 15:37
        $recallTimeTo = $now->copy()->addMinutes(15)->format('H:i'); // +15 minuti

        // Costruisci i parametri per la chiamata API
        // Inseriamo le informazioni aggiuntive nel campo ExtraFields
        $extraFields = sprintf(
            'Fonte=RichiestaContattoMedicoIDM|NomeStudio=%s|Email=%s',
            $validated['nome_studio'],
            $validated['email']
        );

        $params = [
            'ServicePIN' => '00000001',
            'Nominativo' => $nominativo,
            'Telefono' => $telefono,
            'Indirizzo' => '',
            'Citta' => '',
            'Cap' => '',
            'Provincia' => '',
            'Prefisso' => '',
            'ExtraFields' => $extraFields,
            'RecallDate' => $recallDate,
            'RecallTime' => $recallTime,
            'RecallTimeTo' => $recallTimeTo,
        ];

        try {
            // Effettua la chiamata POST all'API esterna
            $response = Http::asForm()->post('https://novarod.telmar.cloud/T2KWEBAPI/WebServices.asmx/AddLeadEX', $params);

            // Verifica se la chiamata è andata a buon fine
            if ($response->successful()) {
                Log::info('✅ Telmar API - SUCCESSO richiesta contatto medico', [
                    'nominativo' => $nominativo,
                    'nome_studio' => $validated['nome_studio'],
                    'email' => $validated['email'],
                    'telefono' => $telefono,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'timestamp' => now()->toDateTimeString(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Richiesta di contatto inviata con successo! Ti contatteremo a breve per discutere della tua iscrizione alla piattaforma.',
                ]);
            } else {
                Log::error('❌ Telmar API - ERRORE richiesta contatto medico', [
                    'nominativo' => $nominativo,
                    'nome_studio' => $validated['nome_studio'],
                    'email' => $validated['email'],
                    'telefono' => $telefono,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'timestamp' => now()->toDateTimeString(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Si è verificato un errore durante l\'invio della richiesta. Riprova più tardi.',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Eccezione durante la chiamata API Telmar (richiesta contatto medico)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Si è verificato un errore imprevisto. Riprova più tardi.',
            ], 500);
        }
    }
}
