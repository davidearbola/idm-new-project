<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListinoMaster;
use App\Models\ListinoMedicoCustomItem;
use App\Models\ListinoMedicoMasterItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ListinoController extends Controller
{
    /**
     * Ritorna il listino completo per il medico autenticato.
     */
    public function index()
    {
        $medico = Auth::user();

        // 1. Prendi tutte le voci master attive
        $vociMaster = ListinoMaster::where('is_active', true)->get();

        // 2. Prendi le personalizzazioni del medico per le voci master
        $medicoMasterItems = $medico->listinoMasterItems()
            ->get()
            ->keyBy('listino_master_id'); // indicizza per un accesso più rapido

        // 3. Unisci le voci master con le personalizzazioni del medico
        $listinoCombinato = $vociMaster->map(function ($voceMaster) use ($medicoMasterItems) {
            $personalizzazione = $medicoMasterItems->get($voceMaster->id);
            return [
                'id' => $voceMaster->id,
                'nome' => $voceMaster->nome,
                'descrizione' => $voceMaster->descrizione,
                'prezzo' => $personalizzazione ? $personalizzazione->prezzo : null,
                'is_active' => $personalizzazione ? $personalizzazione->is_active : true, // Di default è attiva
                'tipo' => 'master'
            ];
        });

        // 4. Prendi le voci custom del medico
        $vociCustom = $medico->listinoCustomItems()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'nome' => $item->nome,
                'descrizione' => $item->descrizione,
                'prezzo' => $item->prezzo,
                'is_active' => true, // Le voci custom sono sempre attive
                'tipo' => 'custom'
            ];
        });

        // 5. Unisci i due listini e ritorna il risultato
        $listinoFinale = $listinoCombinato->merge($vociCustom);

        return response()->json($listinoFinale);
    }

    /**
     * Aggiorna o crea il prezzo/stato di una voce master per il medico.
     */
    public function updateMasterItem(Request $request)
    {
        $medico = Auth::user();
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:listino_master,id',
            'items.*.prezzo' => 'nullable|numeric|min:0',
            'items.*.is_active' => 'required|boolean',
        ]);

        foreach ($validated['items'] as $itemData) {
            $medico->listinoMasterItems()->updateOrCreate(
                ['listino_master_id' => $itemData['id']], // Condizione per trovare il record
                ['prezzo' => $itemData['prezzo'], 'is_active' => $itemData['is_active']] // Valori da aggiornare/creare
            );
        }

        $this->checkListinoCompletion($medico);

        return response()->json(['success' => true, 'message' => 'Voci del listino aggiornate.']);
    }

    /**
     * Crea una nuova voce personalizzata per il medico.
     */
    public function storeCustomItem(Request $request)
    {
        $medico = Auth::user();
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.nome' => 'required|string|max:255',
            'items.*.descrizione' => 'nullable|string',
            'items.*.prezzo' => 'required|numeric|min:0',
        ]);

        foreach ($validated['items'] as $itemData) {
            $medico->listinoCustomItems()->create($itemData);
        }

        return response()->json(['success' => true, 'message' => 'Voci personalizzate aggiunte con successo.'], 201);
    }

    /**
     * Modifica una voce personalizzata del medico.
     */
    public function updateCustomItem(Request $request, ListinoMedicoCustomItem $item)
    {
        // Policy di sicurezza: assicurati che il medico stia modificando solo le sue voci
        if ($item->medico_user_id !== Auth::id()) {
            abort(403, 'Azione non autorizzata.');
        }

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'descrizione' => 'nullable|string',
            'prezzo' => 'required|numeric|min:0',
        ]);

        $item->update($validated);

        return response()->json(['success' => true, 'message' => 'Voce aggiornata.']);
    }

    /**
     * Elimina una voce personalizzata del medico.
     */
    public function destroyCustomItem(ListinoMedicoCustomItem $item)
    {
        if ($item->medico_user_id !== Auth::id()) {
            abort(403, 'Azione non autorizzata.');
        }

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Voce eliminata con successo.']);
    }

    private function checkListinoCompletion($medico)
    {
        $anagrafica = $medico->anagraficaMedico;
        $vociCompletate = $medico->listinoMasterItems()
            ->whereNotNull('prezzo')
            ->where('is_active', true)
            ->count();
        $isComplete = $vociCompletate >= 3;

        if ($isComplete && !$anagrafica->step_listino_completed_at) {
            $anagrafica->update(['step_listino_completed_at' => Carbon::now()]);
        } elseif (!$isComplete && $anagrafica->step_listino_completed_at) {
            $anagrafica->update(['step_listino_completed_at' => null]);
        }
    }
}
