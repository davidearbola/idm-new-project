<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PoltronaMedico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PoltronaController extends Controller
{
    /**
     * Restituisce tutte le poltrone del medico loggato
     */
    public function index()
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $poltrone = PoltronaMedico::where('medico_id', $medico->id)
            ->withCount('disponibilita')
            ->orderBy('nome_poltrona')
            ->get();

        return response()->json($poltrone);
    }

    /**
     * Crea una nuova poltrona
     */
    public function store(Request $request)
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nome_poltrona' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verifica che non esista già una poltrona con lo stesso nome
        $esistente = PoltronaMedico::where('medico_id', $medico->id)
            ->where('nome_poltrona', $request->nome_poltrona)
            ->first();

        if ($esistente) {
            return response()->json([
                'errors' => ['nome_poltrona' => ['Esiste già una poltrona con questo nome']]
            ], 422);
        }

        $poltrona = PoltronaMedico::create([
            'medico_id' => $medico->id,
            'nome_poltrona' => $request->nome_poltrona,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Poltrona creata con successo',
            'data' => $poltrona
        ], 201);
    }

    /**
     * Aggiorna una poltrona
     */
    public function update(Request $request, PoltronaMedico $poltrona)
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico' || $poltrona->medico_id !== $medico->id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nome_poltrona' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verifica che non esista già una poltrona con lo stesso nome (escludendo quella corrente)
        $esistente = PoltronaMedico::where('medico_id', $medico->id)
            ->where('nome_poltrona', $request->nome_poltrona)
            ->where('id', '!=', $poltrona->id)
            ->first();

        if ($esistente) {
            return response()->json([
                'errors' => ['nome_poltrona' => ['Esiste già una poltrona con questo nome']]
            ], 422);
        }

        $poltrona->update([
            'nome_poltrona' => $request->nome_poltrona,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Poltrona aggiornata con successo',
            'data' => $poltrona
        ]);
    }

    /**
     * Elimina una poltrona
     */
    public function destroy(PoltronaMedico $poltrona)
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico' || $poltrona->medico_id !== $medico->id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        try {
            DB::beginTransaction();

            // Verifica se ci sono appuntamenti futuri
            $appuntamentiFuturi = $poltrona->appuntamenti()
                ->where('starting_date_time', '>=', now())
                ->whereIn('stato', ['confermato'])
                ->count();

            if ($appuntamentiFuturi > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Impossibile eliminare: ci sono appuntamenti futuri confermati per questa poltrona'
                ], 400);
            }

            $poltrona->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Poltrona eliminata con successo'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Errore durante l\'eliminazione della poltrona',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
