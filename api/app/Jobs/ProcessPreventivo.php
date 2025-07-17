<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\PreventivoPaziente;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use OpenAI;

class ProcessPreventivo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $preventivo;

    public function __construct(PreventivoPaziente $preventivo)
    {
        $this->preventivo = $preventivo;
    }

    public function handle(): void
    {
        // 1. Aggiorna lo stato a "in elaborazione"
        $this->preventivo->update(['stato_elaborazione' => 'in_elaborazione']);

        $text = null;
        $fileExtension = pathinfo($this->preventivo->file_path, PATHINFO_EXTENSION);

        try {
            $filePath = Storage::disk('public')->path($this->preventivo->file_path);

            // 2. Estrai il testo dal file (PDF o Immagine)
            if (strtolower($fileExtension) === 'pdf') {
                $parser = new Parser();
                $pdf = $parser->parseFile($filePath);
                $text = $pdf->getText();
            } else {
                $response = Http::withHeaders(['apikey' => env('OCR_SPACE_API_KEY')])
                    ->attach('file', file_get_contents($filePath), basename($filePath))
                    ->post('https://api.ocr.space/parse/image', [
                        'language' => 'ita',
                        'isOverlayRequired' => 'false',
                        'detectOrientation' => 'true',
                    ]);

                if ($response->successful() && !$response->json('IsErroredOnProcessing')) {
                    $text = $response->json('ParsedResults.0.ParsedText');
                } else {
                    throw new \Exception('Errore API OCR.space: ' . ($response->json('ErrorMessage.0') ?? 'Errore sconosciuto'));
                }
            }

            if (!$text) {
                throw new \Exception('Estrazione del testo fallita, il risultato è vuoto.');
            }

            // 3. Chiamata OpenAI 
            $client = OpenAI::client(env('OPENAI_API_KEY'));
            $response = $client->chat()->create([
                'model' => 'gpt-4-turbo', // Consigliato per affidabilità con JSON e calcoli
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Sei un assistente che analizza il testo di un preventivo medico/dentistico e lo struttura in un file JSON. Il JSON deve avere due chiavi principali: "voci_preventivo", che è un array di oggetti, e "totale_preventivo", che è un numero. Ogni oggetto in "voci_preventivo" deve avere due chiavi: "prestazione" (stringa) e "prezzo" (numero). Il valore di "totale_preventivo" deve essere la somma matematica di tutti i valori "prezzo" presenti nell\'array. Ignora i dati del paziente e le informazioni non pertinenti alle singole prestazioni. Assicurati che il JSON sia valido.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Analizza, struttura e calcola il totale del seguente testo: \n\n" . $text
                    ]
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

            $structuredJsonString = $response->choices[0]->message->content;
            $structuredJson = json_decode($structuredJsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Il JSON ricevuto da OpenAI non è valido. Risposta: ' . $structuredJsonString);
            }

            // 4. Salva il JSON e aggiorna lo stato nel database
            $this->preventivo->update([
                'json_preventivo'    => $structuredJson,
                'stato_elaborazione' => 'completato'
            ]);

            // 5. Lancia il prossimo job (per ora commentato)
            // GeneraControproposte::dispatch($this->preventivo);

        } catch (\Exception $e) {
            $this->preventivo->update(['stato_elaborazione' => 'errore']);
            Log::error("Errore durante l'elaborazione del preventivo #{$this->preventivo->id}: " . $e->getMessage());
            $this->fail($e);
        }
    }
}