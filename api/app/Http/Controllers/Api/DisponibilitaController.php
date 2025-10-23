<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisponibilitaMedico;
use App\Models\SlotAppuntamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class DisponibilitaController extends Controller
{
    /**
     * Restituisce tutte le disponibilità del medico loggato
     */
    public function index()
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $disponibilita = DisponibilitaMedico::where('medico_id', $medico->id)
            ->orderBy('giorno_settimana')
            ->orderBy('start_time')
            ->get();

        return response()->json($disponibilita);
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
            'giorno_settimana' => 'required|integer|min:1|max:7',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'intervallo_slot' => 'nullable|integer|min:15|max:120',
            'poltrone_disponibili' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $disponibilita = DisponibilitaMedico::create([
            'medico_id' => $medico->id,
            'giorno_settimana' => $request->giorno_settimana,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'intervallo_slot' => $request->intervallo_slot ?? 30,
            'poltrone_disponibili' => $request->poltrone_disponibili,
            'is_active' => true,
        ]);

        // Genera gli slot per i prossimi 3 mesi
        $this->generaSlotsPerDisponibilita($disponibilita);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilità creata con successo',
            'data' => $disponibilita
        ], 201);
    }

    /**
     * Aggiorna una disponibilità
     */
    public function update(Request $request, DisponibilitaMedico $disponibilita)
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico' || $disponibilita->medico_id !== $medico->id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'giorno_settimana' => 'sometimes|integer|min:1|max:7',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'intervallo_slot' => 'sometimes|integer|min:15|max:120',
            'poltrone_disponibili' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $disponibilita->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Disponibilità aggiornata con successo',
            'data' => $disponibilita
        ]);
    }

    /**
     * Elimina una disponibilità
     */
    public function destroy(DisponibilitaMedico $disponibilita)
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico' || $disponibilita->medico_id !== $medico->id) {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        // Elimina anche tutti gli slot futuri non prenotati
        SlotAppuntamento::where('disponibilita_medico_id', $disponibilita->id)
            ->where('poltrone_prenotate', 0)
            ->where('start_time', '>', now())
            ->delete();

        $disponibilita->delete();

        return response()->json([
            'success' => true,
            'message' => 'Disponibilità eliminata con successo'
        ]);
    }

    /**
     * Genera gli slot per una disponibilità nei prossimi 3 mesi
     */
    private function generaSlotsPerDisponibilita(DisponibilitaMedico $disponibilita)
    {
        $oggi = Carbon::today();
        $finePeriodo = Carbon::today()->addMonths(3);

        // Trova tutte le date che corrispondono al giorno della settimana
        $date = [];
        $currentDate = $oggi->copy();

        while ($currentDate->lte($finePeriodo)) {
            if ($currentDate->dayOfWeekIso == $disponibilita->giorno_settimana) {
                $date[] = $currentDate->copy();
            }
            $currentDate->addDay();
        }

        // Per ogni data, crea gli slot
        foreach ($date as $data) {
            $startTime = Carbon::parse($data->format('Y-m-d') . ' ' . $disponibilita->start_time);
            $endTime = Carbon::parse($data->format('Y-m-d') . ' ' . $disponibilita->end_time);

            $currentSlot = $startTime->copy();

            while ($currentSlot->lt($endTime)) {
                $slotEnd = $currentSlot->copy()->addMinutes($disponibilita->intervallo_slot);

                if ($slotEnd->lte($endTime)) {
                    // Verifica se lo slot esiste già
                    $existingSlot = SlotAppuntamento::where('medico_id', $disponibilita->medico_id)
                        ->where('start_time', $currentSlot)
                        ->where('end_time', $slotEnd)
                        ->first();

                    if (!$existingSlot) {
                        SlotAppuntamento::create([
                            'disponibilita_medico_id' => $disponibilita->id,
                            'medico_id' => $disponibilita->medico_id,
                            'start_time' => $currentSlot,
                            'end_time' => $slotEnd,
                            'totale_poltrone' => $disponibilita->poltrone_disponibili,
                            'poltrone_prenotate' => 0,
                        ]);
                    }
                }

                $currentSlot->addMinutes($disponibilita->intervallo_slot);
            }
        }
    }

    /**
     * Genera manualmente gli slot per i prossimi 3 mesi (endpoint per rigenerare)
     */
    public function rigeneraSlots()
    {
        $medico = Auth::user();

        if ($medico->role !== 'medico') {
            return response()->json(['error' => 'Non autorizzato'], 403);
        }

        $disponibilita = DisponibilitaMedico::where('medico_id', $medico->id)
            ->where('is_active', true)
            ->get();

        foreach ($disponibilita as $disp) {
            $this->generaSlotsPerDisponibilita($disp);
        }

        return response()->json([
            'success' => true,
            'message' => 'Slots rigenerati con successo'
        ]);
    }
}
