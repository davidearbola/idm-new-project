<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appuntamento;
use App\Models\SlotAppuntamento;
use App\Models\ContropropostaMedico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppuntamentoController extends Controller
{
    /**
     * Cerca proposte per email o cellulare (per sales)
     */
    public function cercaProposte(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'sales') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'search' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $search = $request->search;

        // Cerca nelle proposte tramite email o cellulare del paziente
        $proposte = ContropropostaMedico::whereHas('preventivoPaziente', function ($query) use ($search) {
            $query->where('email_paziente', 'like', "%{$search}%")
                  ->orWhere('cellulare_paziente', 'like', "%{$search}%");
        })
        ->with([
            'preventivoPaziente',
            'medico.anagraficaMedico'
        ])
        ->where('stato', 'accettata')
        ->get();

        return response()->json($proposte);
    }

    /**
     * Ottiene gli slot disponibili di un medico per un periodo (per sales)
     */
    public function getAgendaMedico(Request $request, $medicoId)
    {
        $user = Auth::user();

        if ($user->role !== 'sales') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'data_inizio' => 'required|date',
            'data_fine' => 'required|date|after_or_equal:data_inizio',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dataInizio = Carbon::parse($request->data_inizio)->startOfDay();
        $dataFine = Carbon::parse($request->data_fine)->endOfDay();

        // Ottiene tutti gli slot del medico nel periodo
        $slots = SlotAppuntamento::where('medico_id', $medicoId)
            ->whereBetween('start_time', [$dataInizio, $dataFine])
            ->with(['appuntamenti'])
            ->orderBy('start_time')
            ->get();

        // Trasforma gli slot per il frontend
        $slotsFormatted = $slots->map(function ($slot) {
            return [
                'id' => $slot->id,
                'start_time' => $slot->start_time->format('Y-m-d H:i:s'),
                'end_time' => $slot->end_time->format('Y-m-d H:i:s'),
                'totale_poltrone' => $slot->totale_poltrone,
                'poltrone_prenotate' => $slot->poltrone_prenotate,
                'disponibile' => $slot->isDisponibile(),
                'poltrone_libere' => $slot->poltronLibere(),
            ];
        });

        return response()->json($slotsFormatted);
    }

    /**
     * Fissa un appuntamento (per sales)
     */
    public function fissaAppuntamento(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'sales') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'slot_appuntamento_id' => 'required|exists:slot_appuntamenti,id',
            'proposta_id' => 'required|exists:controproposte_medici,id',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Verifica che lo slot sia disponibile
            $slot = SlotAppuntamento::findOrFail($request->slot_appuntamento_id);

            if (!$slot->isDisponibile()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Slot non disponibile'
                ], 400);
            }

            // Verifica che la proposta non abbia già un appuntamento
            $appuntamentoEsistente = Appuntamento::where('proposta_id', $request->proposta_id)
                ->whereIn('stato', ['confermato', 'completato'])
                ->first();

            if ($appuntamentoEsistente) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Questa proposta ha già un appuntamento attivo'
                ], 400);
            }

            // Crea l'appuntamento
            $appuntamento = Appuntamento::create([
                'slot_appuntamento_id' => $request->slot_appuntamento_id,
                'medico_id' => $slot->medico_id,
                'proposta_id' => $request->proposta_id,
                'stato' => 'confermato',
                'note' => $request->note,
            ]);

            // Incrementa le poltrone prenotate
            $slot->increment('poltrone_prenotate');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Appuntamento fissato con successo',
                'data' => $appuntamento->load(['slotAppuntamento', 'proposta.preventivoPaziente'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Errore durante la creazione dell\'appuntamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista appuntamenti (per sales e medici)
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['sales', 'medico'])) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $query = Appuntamento::with([
            'slotAppuntamento',
            'medico.anagraficaMedico',
            'proposta.preventivoPaziente'
        ]);

        // Se è un medico, mostra solo i suoi appuntamenti
        if ($user->role === 'medico') {
            $query->where('medico_id', $user->id);
        }

        // Filtra per stato se richiesto
        if ($request->has('stato')) {
            $query->where('stato', $request->stato);
        }

        // Ordina per data slot
        $query->join('slot_appuntamenti', 'appuntamenti.slot_appuntamento_id', '=', 'slot_appuntamenti.id')
            ->orderBy('slot_appuntamenti.start_time', 'desc')
            ->select('appuntamenti.*');

        $appuntamenti = $query->get();

        return response()->json($appuntamenti);
    }

    /**
     * Aggiorna stato appuntamento
     */
    public function updateStato(Request $request, Appuntamento $appuntamento)
    {
        $user = Auth::user();

        // Solo il medico proprietario o sales possono aggiornare
        if ($user->role !== 'sales' && $appuntamento->medico_id !== $user->id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'stato' => 'required|in:confermato,completato,cancellato',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $vecchioStato = $appuntamento->stato;
            $nuovoStato = $request->stato;

            // Se si cancella un appuntamento, libera la poltrona
            if ($nuovoStato === 'cancellato' && $vecchioStato !== 'cancellato') {
                $appuntamento->slotAppuntamento->decrement('poltrone_prenotate');
            }

            $appuntamento->update([
                'stato' => $nuovoStato,
                'note' => $request->note ?? $appuntamento->note,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Appuntamento aggiornato con successo',
                'data' => $appuntamento->load(['slotAppuntamento', 'proposta.preventivoPaziente'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Errore durante l\'aggiornamento dell\'appuntamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ottiene gli appuntamenti futuri del medico loggato
     */
    public function getAppuntamentiFuturi()
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $appuntamenti = Appuntamento::where('appuntamenti.medico_id', $medico->id)
            ->whereIn('appuntamenti.stato', ['confermato'])
            ->whereHas('slotAppuntamento', function ($query) {
                $query->where('start_time', '>=', now());
            })
            ->with([
                'slotAppuntamento',
                'proposta.preventivoPaziente'
            ])
            ->join('slot_appuntamenti', 'appuntamenti.slot_appuntamento_id', '=', 'slot_appuntamenti.id')
            ->orderBy('slot_appuntamenti.start_time', 'asc')
            ->select('appuntamenti.*')
            ->get();

        return response()->json($appuntamenti);
    }
}
