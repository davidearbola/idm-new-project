<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisponibilitaMedico;
use App\Models\PoltronaMedico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DisponibilitaController extends Controller
{
    /**
     * Restituisce tutte le disponibilità del medico loggato raggruppate per poltrona
     */
    public function index()
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        // Carica le poltrone con le relative disponibilità
        $poltrone = PoltronaMedico::where('medico_id', $medico->id)
            ->with(['disponibilita' => function ($query) {
                $query->orderBy('giorno_settimana')->orderBy('starting_time');
            }])
            ->orderBy('nome_poltrona')
            ->get();

        return response()->json($poltrone);
    }

    /**
     * Crea una nuova disponibilità
     */
    public function store(Request $request)
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'poltrona_id' => 'required|exists:poltrone_medici,id',
            'giorno_settimana' => 'required|integer|min:1|max:7',
            'starting_time' => 'required|date_format:H:i',
            'ending_time' => 'required|date_format:H:i|after:starting_time',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verifica che la poltrona appartenga al medico
        $poltrona = PoltronaMedico::find($request->poltrona_id);
        if ($poltrona->medico_id !== $medico->id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        // Verifica sovrapposizioni di orari per la stessa poltrona e giorno
        $sovrapposizione = DisponibilitaMedico::where('poltrona_id', $request->poltrona_id)
            ->where('giorno_settimana', $request->giorno_settimana)
            ->where(function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('starting_time', '<', $request->ending_time)
                      ->where('ending_time', '>', $request->starting_time);
                });
            })
            ->exists();

        if ($sovrapposizione) {
            return response()->json([
                'errors' => ['starting_time' => ['Esiste già una disponibilità in questo orario per questa poltrona']]
            ], 422);
        }

        $disponibilita = DisponibilitaMedico::create([
            'poltrona_id' => $request->poltrona_id,
            'intervallo_minuti' => 30, // Fisso a 30 minuti
            'giorno_settimana' => $request->giorno_settimana,
            'starting_time' => $request->starting_time,
            'ending_time' => $request->ending_time,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilità creata con successo',
            'data' => $disponibilita->load('poltrona')
        ], 201);
    }

    /**
     * Aggiorna una disponibilità
     */
    public function update(Request $request, DisponibilitaMedico $disponibilita)
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico' || $disponibilita->poltrona->medico_id !== $medico->id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'giorno_settimana' => 'sometimes|integer|min:1|max:7',
            'starting_time' => 'sometimes|date_format:H:i',
            'ending_time' => 'sometimes|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verifica che ending_time sia dopo starting_time
        $startingTime = $request->has('starting_time') ? $request->starting_time : $disponibilita->starting_time;
        $endingTime = $request->has('ending_time') ? $request->ending_time : $disponibilita->ending_time;

        if ($endingTime <= $startingTime) {
            return response()->json([
                'errors' => ['ending_time' => ['L\'orario di fine deve essere successivo all\'orario di inizio']]
            ], 422);
        }

        // Verifica sovrapposizioni (escludendo la disponibilità corrente)
        $giornoSettimana = $request->has('giorno_settimana') ? $request->giorno_settimana : $disponibilita->giorno_settimana;

        $sovrapposizione = DisponibilitaMedico::where('poltrona_id', $disponibilita->poltrona_id)
            ->where('giorno_settimana', $giornoSettimana)
            ->where('id', '!=', $disponibilita->id)
            ->where(function ($query) use ($startingTime, $endingTime) {
                $query->where(function ($q) use ($startingTime, $endingTime) {
                    $q->where('starting_time', '<', $endingTime)
                      ->where('ending_time', '>', $startingTime);
                });
            })
            ->exists();

        if ($sovrapposizione) {
            return response()->json([
                'errors' => ['starting_time' => ['Esiste già una disponibilità in questo orario per questa poltrona']]
            ], 422);
        }

        $disponibilita->update($request->only([
            'giorno_settimana',
            'starting_time',
            'ending_time'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Disponibilità aggiornata con successo',
            'data' => $disponibilita->load('poltrona')
        ]);
    }

    /**
     * Elimina una disponibilità
     */
    public function destroy(DisponibilitaMedico $disponibilita)
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico' || $disponibilita->poltrona->medico_id !== $medico->id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        try {
            DB::beginTransaction();

            // Verifica se ci sono appuntamenti futuri per questa fascia oraria
            if ($disponibilita->hasAppuntamentiFuturi()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Impossibile eliminare: ci sono appuntamenti futuri in questa fascia oraria'
                ], 400);
            }

            $disponibilita->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Disponibilità eliminata con successo'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Errore durante l\'eliminazione della disponibilità',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
