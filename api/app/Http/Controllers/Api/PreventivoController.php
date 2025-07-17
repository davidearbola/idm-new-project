<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPreventivo;
use App\Models\AnagraficaPaziente;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PreventivoController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // --- 1. Validazione dei Dati ---
        // Controlliamo se l'utente ha già un'anagrafica per decidere se i campi sono obbligatori
        $anagraficaExists = $request->user()->anagraficaPaziente()->exists();

        $rules = [
            'preventivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // max 10MB
        ];

        // Se l'anagrafica NON esiste, rendiamo i campi obbligatori
        if (!$anagraficaExists) {
            $rules += [
                'cellulare' => 'required|string|min:9',
                'indirizzo' => 'required|string|max:255',
                'citta' => 'required|string|max:255',
                'cap' => 'required|string|size:5',
                'provincia' => 'required|string|size:2',
            ];
        }

        $validatedData = $request->validate($rules);

        // --- 2. Gestione Anagrafica ---
        if (!$anagraficaExists) {
            // Crea una nuova anagrafica se non esiste
            $anagrafica = $request->user()->anagraficaPaziente()->create([
                'cellulare' => $validatedData['cellulare'],
                'indirizzo' => $validatedData['indirizzo'],
                'citta'     => $validatedData['citta'],
                'cap'       => $validatedData['cap'],
                'provincia' => $validatedData['provincia'],
                'lat'       => 45.4642000,
                'lng'       => 9.1900000,
            ]);
        } else {
            // Altrimenti, usa l'anagrafica esistente
            $anagrafica = $request->user()->anagraficaPaziente;
        }

        // --- 3. Salvataggio del File ---
        $file = $request->file('preventivo');

        // Creiamo un nome univoco per il file
        $fileName = Str::slug($request->user()->name) . '_' . $anagrafica->id . '_' . time() . '.' . $file->getClientOriginalExtension();

        // Salviamo il file in una cartella specifica per l'utente (basata sull'ID anagrafica)
        $filePath = $file->storeAs('preventivi/' . $anagrafica->id, $fileName, 'public');

        // --- 4. Creazione del Record Preventivo ---
        $preventivo = $anagrafica->preventivi()->create([
            'file_path'           => $filePath,
            'file_name_originale' => $file->getClientOriginalName(),
            'stato_elaborazione'  => 'caricato',
        ]);

        // --- 5. Dispatch del Job in Background ---
        ProcessPreventivo::dispatch($preventivo);

        // --- 6. Risposta di Successo ---
        return response()->json([
            'success' => true,
            'message' => 'Preventivo caricato con successo! Lo stiamo elaborando per te.',
        ], 201);
    }
}
