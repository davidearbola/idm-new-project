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

            // Verifica se ci sono appuntamenti futuri attivi (escludendo solo quelli cancellati o assente)
            $appuntamentiFuturi = $poltrona->appuntamenti()
                ->where('starting_date_time', '>=', now())
                ->whereNotIn('stato', ['cancellato', 'assente'])
                ->count();

            if ($appuntamentiFuturi > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Impossibile eliminare: ci sono appuntamenti futuri attivi per questa poltrona'
                ], 400);
            }

            // Le disponibilità verranno automaticamente eliminate grazie al cascade nella loro foreign key
            // Gli appuntamenti invece avranno poltrona_id = NULL grazie al nullOnDelete

            // Elimina la poltrona
            $poltrona->delete();

            // Verifica e aggiorna lo step agenda (potrebbe essere da rimuovere se non ci sono più poltrone con disponibilità)
            $this->verificaEComletaStepAgenda($medico);

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

    /**
     * Verifica se il medico ha almeno 1 poltrona con almeno 1 disponibilità
     * e completa lo step_agenda_completed_at se necessario
     */
    private function verificaEComletaStepAgenda($medico)
    {
        // Verifica se ci sono poltrone con almeno una disponibilità
        $hasPoltronaConDisponibilita = PoltronaMedico::where('medico_id', $medico->id)
            ->whereHas('disponibilita')
            ->exists();

        $anagrafica = $medico->anagraficaMedico;

        if ($hasPoltronaConDisponibilita) {
            // Se ha almeno una poltrona con disponibilità e lo step non è completato, completalo
            if (!$anagrafica->step_agenda_completed_at) {
                $anagrafica->update(['step_agenda_completed_at' => now()]);
            }
        } else {
            // Se non ha più poltrone con disponibilità, rimuovi il completamento dello step
            if ($anagrafica->step_agenda_completed_at) {
                $anagrafica->update(['step_agenda_completed_at' => null]);
            }
        }
    }
}
