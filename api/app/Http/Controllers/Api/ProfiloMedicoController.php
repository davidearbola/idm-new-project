<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FotoStudio;
use App\Models\StaffMedico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfiloMedicoController extends Controller
{
    /**
     * Ritorna tutti i dati del profilo del medico.
     */
    public function index()
    {
        $medico = Auth::user();
        $medico->load(['anagraficaMedico', 'fotoStudi', 'staff']);

        return response()->json($medico);
    }

    /**
     * Aggiorna la descrizione dello studio.
     */
    public function updateDescrizione(Request $request)
    {
        $validated = $request->validate([
            'descrizione' => 'required|string|min:50',
        ]);

        Auth::user()->anagraficaMedico()->update($validated);

        return response()->json(['success' => true, 'message' => 'Descrizione aggiornata con successo.']);
    }

    /**
     * Carica una nuova foto per lo studio.
     */
    public function uploadFotoStudio(Request $request)
    {
        $validated = $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png|max:2048', // max 2MB
        ]);

        $medico = Auth::user();
        $file = $validated['foto'];

        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('profilo_studi/' . $medico->id . '/foto_studio', $fileName, 'public');

        $foto = $medico->fotoStudi()->create(['file_path' => $filePath]);

        return response()->json([
            'success' => true,
            'message' => 'Foto caricata con successo.',
            'foto' => $foto
        ], 201);
    }

    /**
     * Elimina una foto dello studio.
     */
    public function destroyFotoStudio(FotoStudio $foto)
    {
        if ($foto->medico_user_id !== Auth::id()) {
            abort(403, 'Azione non autorizzata.');
        }

        // Elimina il file dallo storage
        Storage::disk('public')->delete($foto->file_path);
        // Elimina il record dal database
        $foto->delete();

        return response()->json(['success' => true, 'message' => 'Foto eliminata.']);
    }

    /**
     * Crea un nuovo membro dello staff.
     */
    public function storeStaff(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'ruolo' => 'required|string|max:255',
            'specializzazione' => 'nullable|string',
            'esperienza' => 'nullable|string',
            'foto' => 'required|image|mimes:jpg,jpeg,png|max:1024', // max 1MB
        ]);

        $medico = Auth::user();
        $file = $validated['foto'];

        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('profilo_studi/' . $medico->id . '/foto_staff', $fileName, 'public');

        $staff = $medico->staff()->create([
            'nome' => $validated['nome'],
            'ruolo' => $validated['ruolo'],
            'specializzazione' => $validated['specializzazione'],
            'esperienza' => $validated['esperienza'],
            'foto_path' => $filePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Membro dello staff aggiunto.',
            'staff' => $staff,
        ], 201);
    }

    /**
     * Aggiorna un membro dello staff 
     */
    public function updateStaff(Request $request, StaffMedico $staff)
    {
        if ($staff->medico_user_id !== Auth::id()) {
            abort(403, 'Azione non autorizzata.');
        }

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'ruolo' => 'required|string|max:255',
            'specializzazione' => 'nullable|string',
            'esperienza' => 'nullable|string',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:1024', // La foto è facoltativa in modifica
        ]);

        // Gestione della foto, se ne è stata caricata una nuova
        if ($request->hasFile('foto')) {
            // 1. Elimina la vecchia foto dallo storage
            if ($staff->foto_path) {
                Storage::disk('public')->delete($staff->foto_path);
            }

            // 2. Salva la nuova foto
            $file = $request->file('foto');
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('profilo_studi/' . Auth::id() . '/foto_staff', $fileName, 'public');

            // 3. Aggiunge il nuovo percorso ai dati da salvare
            $validated['foto_path'] = $filePath;
        }

        // 4. Aggiorna il record nel database
        $staff->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Membro dello staff aggiornato.',
            'staff' => $staff->fresh() // Ritorna i dati aggiornati
        ]);
    }

    /**
     * Elimina un membro dello staff.
     */
    public function destroyStaff(StaffMedico $staff)
    {
        if ($staff->medico_user_id !== Auth::id()) {
            abort(403, 'Azione non autorizzata.');
        }
        Storage::disk('public')->delete($staff->foto_path);
        $staff->delete();
        return response()->json(['success' => true, 'message' => 'Membro dello staff eliminato.']);
    }
}
