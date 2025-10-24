<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appuntamento;
use App\Models\PoltronaMedico;
use App\Models\DisponibilitaMedico;
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
     * Ottiene l'agenda settimanale del medico con disponibilità e appuntamenti (per sales)
     */
    public function getAgendaMedico(Request $request, $medicoId)
    {
        $user = Auth::user();

        if ($user->role !== 'sales') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'data_inizio' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dataInizio = Carbon::parse($request->data_inizio)->startOfDay();

        // Assicurati che dataInizio sia sempre un Lunedì
        if ($dataInizio->dayOfWeekIso !== 1) {
            $dataInizio = $dataInizio->startOfWeek(Carbon::MONDAY);
        }

        // Calcola i 7 giorni della settimana
        $giorniSettimana = [];
        for ($i = 0; $i < 7; $i++) {
            $giorno = $dataInizio->copy()->addDays($i);
            $giorniSettimana[] = [
                'data' => $giorno->format('Y-m-d'),
                'giorno_settimana' => $giorno->dayOfWeekIso,
                'nome_giorno' => $giorno->locale('it')->isoFormat('dddd'),
            ];
        }

        // Recupera tutte le poltrone del medico con le loro disponibilità
        $poltrone = PoltronaMedico::where('medico_id', $medicoId)
            ->with('disponibilita')
            ->get();

        // Recupera tutti gli appuntamenti del medico nel periodo
        $appuntamenti = Appuntamento::whereHas('poltrona', function ($query) use ($medicoId) {
            $query->where('medico_id', $medicoId);
        })
        ->whereBetween('starting_date_time', [
            $dataInizio,
            $dataInizio->copy()->addDays(6)->endOfDay()
        ])
        ->whereIn('stato', ['nuovo', 'visualizzato'])
        ->with(['proposta.preventivoPaziente', 'poltrona'])
        ->get();

        // Costruisce la struttura dati per il frontend
        $agenda = [];

        foreach ($poltrone as $poltrona) {
            $agendaPoltrona = [
                'poltrona_id' => $poltrona->id,
                'nome_poltrona' => $poltrona->nome_poltrona,
                'giorni' => []
            ];

            foreach ($giorniSettimana as $giorno) {
                $slots = [];

                // Trova le disponibilità per questo giorno e questa poltrona
                $dispGiorno = $poltrona->disponibilita->filter(function ($disp) use ($giorno) {
                    return $disp->giorno_settimana == $giorno['giorno_settimana'];
                });

                foreach ($dispGiorno as $disp) {
                    // Genera gli slot di 30 minuti
                    $startTime = Carbon::parse($giorno['data'] . ' ' . $disp->starting_time);
                    $endTime = Carbon::parse($giorno['data'] . ' ' . $disp->ending_time);

                    $currentSlot = $startTime->copy();
                    while ($currentSlot->lt($endTime)) {
                        $slotEnd = $currentSlot->copy()->addMinutes(30);

                        if ($slotEnd->lte($endTime)) {
                            // Verifica se c'è un appuntamento in questo slot per questa poltrona
                            $appuntamento = $appuntamenti->first(function ($app) use ($currentSlot, $slotEnd, $poltrona) {
                                if ($app->poltrona_id !== $poltrona->id) {
                                    return false;
                                }

                                $appStart = Carbon::parse($app->starting_date_time);
                                $appEnd = Carbon::parse($app->ending_date_time);

                                return ($appStart->lt($slotEnd) && $appEnd->gt($currentSlot));
                            });

                            $slots[] = [
                                'starting_time' => $currentSlot->format('H:i'),
                                'ending_time' => $slotEnd->format('H:i'),
                                'starting_datetime' => $currentSlot->format('Y-m-d H:i:s'),
                                'ending_datetime' => $slotEnd->format('Y-m-d H:i:s'),
                                'disponibile' => !$appuntamento,
                                'appuntamento' => $appuntamento ? [
                                    'id' => $appuntamento->id,
                                    'paziente' => $appuntamento->proposta->preventivoPaziente->nome_paziente . ' ' .
                                                 $appuntamento->proposta->preventivoPaziente->cognome_paziente,
                                    'stato' => $appuntamento->stato,
                                ] : null,
                            ];
                        }

                        $currentSlot->addMinutes(30);
                    }
                }

                $agendaPoltrona['giorni'][] = [
                    'data' => $giorno['data'],
                    'nome_giorno' => $giorno['nome_giorno'],
                    'slots' => $slots,
                ];
            }

            $agenda[] = $agendaPoltrona;
        }

        return response()->json([
            'poltrone' => $agenda,
            'periodo' => [
                'data_inizio' => $dataInizio->format('Y-m-d'),
                'data_fine' => $dataInizio->copy()->addDays(6)->format('Y-m-d'),
            ]
        ]);
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
            'proposta_id' => 'required|exists:controproposte_medici,id',
            'poltrona_id' => 'required|exists:poltrone_medici,id',
            'starting_date_time' => 'required|date',
            'ending_date_time' => 'required|date|after:starting_date_time',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $startingDateTime = Carbon::parse($request->starting_date_time);
            $endingDateTime = Carbon::parse($request->ending_date_time);

            // Verifica che la proposta non abbia già un appuntamento futuro attivo
            // Solo appuntamenti con stato 'nuovo' o 'visualizzato' e data futura bloccano nuovi appuntamenti
            $appuntamentoEsistente = Appuntamento::where('proposta_id', $request->proposta_id)
                ->whereIn('stato', ['nuovo', 'visualizzato'])
                ->where('starting_date_time', '>=', now())
                ->first();

            if ($appuntamentoEsistente) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Questa proposta ha già un appuntamento futuro attivo'
                ], 400);
            }

            // Verifica che non ci siano sovrapposizioni con altri appuntamenti per questa poltrona
            // Solo appuntamenti non cancellati contano per le sovrapposizioni
            $sovrapposizione = Appuntamento::where('poltrona_id', $request->poltrona_id)
                ->whereIn('stato', ['nuovo', 'visualizzato'])
                ->where(function ($query) use ($startingDateTime, $endingDateTime) {
                    $query->where(function ($q) use ($startingDateTime, $endingDateTime) {
                        $q->where('starting_date_time', '<', $endingDateTime)
                          ->where('ending_date_time', '>', $startingDateTime);
                    });
                })
                ->exists();

            if ($sovrapposizione) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Questo slot è già occupato da un altro appuntamento'
                ], 400);
            }

            // Crea l'appuntamento
            $appuntamento = Appuntamento::create([
                'proposta_id' => $request->proposta_id,
                'poltrona_id' => $request->poltrona_id,
                'starting_date_time' => $startingDateTime,
                'ending_date_time' => $endingDateTime,
                'stato' => 'nuovo',
                'note' => $request->note,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Appuntamento fissato con successo',
                'data' => $appuntamento->load(['proposta.preventivoPaziente', 'poltrona'])
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
            'poltrona.medico.anagraficaMedico',
            'proposta.medico.anagraficaMedico',
            'proposta.preventivoPaziente'
        ]);

        // Se è un medico, mostra solo i suoi appuntamenti
        if ($user->role === 'medico') {
            $query->whereHas('poltrona', function ($q) use ($user) {
                $q->where('medico_id', $user->id);
            });
        }

        // Filtra per stato se richiesto
        if ($request->has('stato')) {
            $query->where('stato', $request->stato);
        }

        // Ordina per data (dal più recente)
        $query->orderBy('starting_date_time', 'desc');

        $appuntamenti = $query->get();

        return response()->json($appuntamenti);
    }

    /**
     * Aggiorna stato appuntamento
     */
    public function updateStato(Request $request, Appuntamento $appuntamento)
    {
        $user = Auth::user();

        // Verifica autorizzazione
        if ($user->role === 'medico') {
            if ($appuntamento->poltrona->medico_id !== $user->id) {
                return response()->json(['error' => 'Non autorizzato'], 403);
            }
        } elseif ($user->role !== 'sales') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'stato' => 'required|in:nuovo,visualizzato,assente,cancellato',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $appuntamento->update([
            'stato' => $request->stato,
            'note' => $request->note ?? $appuntamento->note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Appuntamento aggiornato con successo',
            'data' => $appuntamento->load(['proposta.preventivoPaziente', 'poltrona'])
        ]);
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

        $appuntamenti = Appuntamento::whereHas('poltrona', function ($query) use ($medico) {
            $query->where('medico_id', $medico->id);
        })
        ->whereIn('stato', ['nuovo', 'visualizzato'])
        ->where('starting_date_time', '>=', now())
        ->with(['proposta.preventivoPaziente', 'poltrona'])
        ->orderBy('starting_date_time', 'asc')
        ->get();

        return response()->json($appuntamenti);
    }

    /**
     * Marca un appuntamento come visualizzato (solo per medici)
     */
    public function marcaVisualizzato(Appuntamento $appuntamento)
    {
        $user = Auth::user();

        if ($user->role !== 'medico') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        // Verifica che l'appuntamento appartenga al medico
        if ($appuntamento->poltrona->medico_id !== $user->id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        // Aggiorna lo stato solo se è 'nuovo'
        if ($appuntamento->stato === 'nuovo') {
            $appuntamento->update(['stato' => 'visualizzato']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Appuntamento marcato come visualizzato',
            'data' => $appuntamento->load(['proposta.preventivoPaziente', 'poltrona'])
        ]);
    }
}
