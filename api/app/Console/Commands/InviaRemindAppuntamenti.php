<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appuntamento;
use App\Notifications\AppuntamentoRemindPazienteNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class InviaRemindAppuntamenti extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:invia-remind-appuntamenti';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invia email di remind ai pazienti 2 giorni prima dell\'appuntamento';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('[RemindAppuntamenti] Inizio esecuzione comando alle ' . now());

        try {
            // Data di domani + 1 giorno = 2 giorni da oggi
            $dataDaControllare = Carbon::now()->addDays(2)->startOfDay();
            $fineDaControllare = $dataDaControllare->copy()->endOfDay();

            Log::info('[RemindAppuntamenti] Controllo appuntamenti per il ' . $dataDaControllare->format('Y-m-d'));

            // Trova tutti gli appuntamenti tra 2 giorni che non hanno ancora ricevuto il remind
            $appuntamenti = Appuntamento::with([
                'proposta.preventivoPaziente',
                'poltrona.medico.anagraficaMedico'
            ])
            ->whereBetween('starting_date_time', [$dataDaControllare, $fineDaControllare])
            ->whereIn('stato', ['nuovo', 'visualizzato'])
            ->where('remind_send', 0)
            ->get();

            Log::info('[RemindAppuntamenti] Trovati ' . $appuntamenti->count() . ' appuntamenti da processare');

            $inviati = 0;
            $errori = 0;

            foreach ($appuntamenti as $appuntamento) {
                try {
                    $preventivo = $appuntamento->proposta->preventivoPaziente;
                    $medico = $appuntamento->poltrona->medico;
                    $anagraficaMedico = $medico->anagraficaMedico;

                    if (!$preventivo) {
                        Log::warning('[RemindAppuntamenti] Preventivo non trovato per appuntamento ID: ' . $appuntamento->id);
                        $errori++;
                        continue;
                    }

                    if (!$preventivo->email_paziente) {
                        Log::warning('[RemindAppuntamenti] Email paziente non trovata per appuntamento ID: ' . $appuntamento->id);
                        $errori++;
                        continue;
                    }

                    if (!$anagraficaMedico) {
                        Log::warning('[RemindAppuntamenti] Anagrafica medico non trovata per appuntamento ID: ' . $appuntamento->id);
                        $errori++;
                        continue;
                    }

                    Log::info('[RemindAppuntamenti] Invio remind a: ' . $preventivo->email_paziente . ' per appuntamento ID: ' . $appuntamento->id);

                    // Invia la notifica
                    Notification::route('mail', $preventivo->email_paziente)
                        ->notify(new AppuntamentoRemindPazienteNotification(
                            $appuntamento,
                            $preventivo->nome_paziente ?? '',
                            $preventivo->cognome_paziente ?? '',
                            $anagraficaMedico
                        ));

                    // Marca come inviato
                    $appuntamento->update(['remind_send' => 1]);

                    $inviati++;
                    Log::info('[RemindAppuntamenti] Remind inviato con successo per appuntamento ID: ' . $appuntamento->id);

                } catch (\Exception $e) {
                    $errori++;
                    Log::error('[RemindAppuntamenti] Errore invio remind per appuntamento ID: ' . $appuntamento->id . ' - ' . $e->getMessage());
                }
            }

            Log::info('[RemindAppuntamenti] Comando completato. Inviati: ' . $inviati . ', Errori: ' . $errori);
            $this->info('Comando completato. Inviati: ' . $inviati . ', Errori: ' . $errori);

        } catch (\Exception $e) {
            Log::error('[RemindAppuntamenti] Errore generale: ' . $e->getMessage());
            $this->error('Errore durante l\'esecuzione: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
