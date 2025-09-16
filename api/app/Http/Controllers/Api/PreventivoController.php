<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPreventivo;
use Illuminate\Http\Request;
use Illuminate\Http\File; // <-- IMPORTANTE: Aggiungi questo "use" statement
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PreventivoController extends Controller
{
    /**
     * Salva un nuovo preventivo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Log iniziale per tracciare la ricezione del file
        if ($request->hasFile('preventivo')) {
            $sizeInKb = round($request->file('preventivo')->getSize() / 1024, 2);
            Log::info("Nuovo preventivo ricevuto. Dimensione file: {$sizeInKb} KB");
        }

        // --- 1. VALIDAZIONE DEI DATI ---
        $anagraficaExists = $request->user()->anagraficaPaziente()->exists();

        $rules = [
            'preventivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // max 10MB
        ];

        // Se l'anagrafica del paziente non esiste, i suoi dati diventano obbligatori.
        if (!$anagraficaExists) {
            $rules += [
                'cellulare' => 'required|string|min:9',
                'indirizzo' => 'required|string|max:255',
                'citta'     => 'required|string|max:255',
                'cap'       => 'required|string|size:5',
                'provincia' => 'required|string|size:2',
            ];
        }

        $validatedData = $request->validate($rules);

        // --- 2. GESTIONE ANAGRAFICA PAZIENTE ---
        // Questa logica viene eseguita prima del salvataggio del file,
        // così abbiamo sempre un ID anagrafica a disposizione per creare il percorso.
        if (!$anagraficaExists) {
            // Se non esiste, la creiamo.
            $anagrafica = $request->user()->anagraficaPaziente()->create([
                'cellulare' => $validatedData['cellulare'],
                'indirizzo' => $validatedData['indirizzo'],
                'citta'     => $validatedData['citta'],
                'cap'       => $validatedData['cap'],
                'provincia' => $validatedData['provincia'],
                'lat'       => 45.4642000, // Valori di default
                'lng'       => 9.1900000,
            ]);
        } else {
            // Se esiste già, la recuperiamo.
            $anagrafica = $request->user()->anagraficaPaziente;
        }

        // --- 3. GESTIONE E SALVATAGGIO DEL FILE ---
        $file = $request->file('preventivo');
        $maxSizeInBytes = 1024 * 1024; // 1MB
        $filePath = null; // Inizializziamo la variabile che conterrà il percorso finale del file

        // Controlliamo se è un'immagine e se la sua dimensione supera il limite di 1MB
        if (Str::startsWith($file->getMimeType(), 'image/') && $file->getSize() > $maxSizeInBytes) {
            // --- Caso A: L'immagine è grande e va ridimensionata ---

            $sourcePath = $file->getRealPath();
            list($originalWidth, $originalHeight, $imageType) = getimagesize($sourcePath);

            $sourceImage = null;
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
            }

            // Procediamo solo se il formato immagine è supportato (JPEG o PNG)
            if ($sourceImage) {
                // Calcoliamo le nuove dimensioni mantenendo le proporzioni
                $maxWidth = 1200; // Larghezza massima desiderata
                $ratio = $originalWidth / $originalHeight;
                $newWidth = $maxWidth;
                $newHeight = $maxWidth / $ratio;

                // Creiamo una nuova immagine vuota
                $destImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

                // Creiamo un percorso temporaneo per salvare l'immagine ridimensionata
                $tempPath = tempnam(sys_get_temp_dir(), 'resized-') . '.' . $file->getClientOriginalExtension();

                // Salviamo l'immagine nel percorso temporaneo con una data qualità/compressione
                switch ($imageType) {
                    case IMAGETYPE_JPEG:
                        imagejpeg($destImage, $tempPath, 85); // Qualità 85%
                        break;
                    case IMAGETYPE_PNG:
                        imagepng($destImage, $tempPath, 6); // Compressione livello 6
                        break;
                }

                // Liberiamo la memoria usata dalle risorse immagine GD
                imagedestroy($sourceImage);
                imagedestroy($destImage);

                // Prepariamo il nome e la cartella finale
                $fileName = Str::slug($request->user()->name) . '_' . $anagrafica->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $finalDirectory = 'preventivi/' . $anagrafica->id;

                // **SALVATAGGIO CORRETTO**: Usiamo Storage::putFileAs per salvare il file temporaneo ridimensionato
                Storage::disk('public')->putFileAs($finalDirectory, new File($tempPath), $fileName);
                $filePath = $finalDirectory . '/' . $fileName;

                // Rimuoviamo il file temporaneo perché ora è stato salvato permanentemente
                unlink($tempPath);
            }
        }

        // Se $filePath è ancora nullo, significa che non siamo entrati nel blocco di ridimensionamento.
        // Questo accade se il file è un PDF o un'immagine già più piccola di 1MB.
        if (is_null($filePath)) {
            // --- Caso B: Salvataggio standard del file originale ---
            $fileName = Str::slug($request->user()->name) . '_' . $anagrafica->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('preventivi/' . $anagrafica->id, $fileName, 'public');
        }

        // --- 4. CREAZIONE RECORD NEL DATABASE ---
        $preventivo = $anagrafica->preventivi()->create([
            'file_path'           => $filePath, // Usiamo il percorso finale, che sia del file ridimensionato o originale
            'file_name_originale' => $file->getClientOriginalName(),
            'stato_elaborazione'  => 'caricato',
        ]);

        // --- 5. DISPATCH DEL JOB IN BACKGROUND ---
        ProcessPreventivo::dispatch($preventivo);

        // --- 6. RISPOSTA DI SUCCESSO ---
        return response()->json([
            'success' => true,
            'message' => 'Preventivo caricato con successo! Lo stiamo elaborando per te.',
        ], 201);
    }
}