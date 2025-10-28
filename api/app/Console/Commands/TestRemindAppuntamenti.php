<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appuntamento;
use App\Notifications\AppuntamentoRemindPazienteNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class TestRemindAppuntamenti extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-remind-appuntamenti {--date= : Data specifica per testare (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test comando remind appuntamenti con una data specifica';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== TEST REMIND APPUNTAMENTI ===');

        // Se non viene passata una data, cerca il prossimo appuntamento futuro
        if ($this->option('date')) {
            $dataDaControllare = Carbon::parse($this->option('date'))->startOfDay();
        } else {
            // Trova il primo appuntamento futuro
            $primoAppuntamento = Appuntamento::whereIn('stato', ['nuovo', 'visualizzato'])
                ->where('starting_date_time', '>=', now())
                ->orderBy('starting_date_time', 'asc')
                ->first();

            if (!$primoAppuntamento) {
                $this->error('Nessun appuntamento futuro trovato nel database');
                return 1;
            }

            $dataDaControllare = Carbon::parse($primoAppuntamento->starting_date_time)->startOfDay();
            $this->info('Trovato appuntamento per il: ' . $dataDaControllare->format('Y-m-d'));
        }

        $fineDaControllare = $dataDaControllare->copy()->endOfDay();

        $this->info('Controllo appuntamenti per il: ' . $dataDaControllare->format('Y-m-d'));

        // Trova tutti gli appuntamenti per la data specificata (ignora remind_send per il test)
        $appuntamenti = Appuntamento::with([
            'proposta.preventivoPaziente',
            'poltrona.medico.anagraficaMedico'
        ])
        ->whereBetween('starting_date_time', [$dataDaControllare, $fineDaControllare])
        ->whereIn('stato', ['nuovo', 'visualizzato'])
        ->get();

        $this->info('Trovati ' . $appuntamenti->count() . ' appuntamenti');

        if ($appuntamenti->isEmpty()) {
            $this->warn('Nessun appuntamento trovato per questa data');
            return 0;
        }

        foreach ($appuntamenti as $appuntamento) {
            $preventivo = $appuntamento->proposta->preventivoPaziente;
            $medico = $appuntamento->poltrona->medico;
            $anagraficaMedico = $medico->anagraficaMedico;

            $this->info('');
            $this->info('Appuntamento ID: ' . $appuntamento->id);
            $this->info('Data: ' . Carbon::parse($appuntamento->starting_date_time)->format('d-m-Y H:i'));
            $this->info('Paziente: ' . ($preventivo->nome_paziente ?? '') . ' ' . ($preventivo->cognome_paziente ?? ''));
            $this->info('Email: ' . ($preventivo->email_paziente ?? 'N/A'));
            $this->info('Studio: ' . ($anagraficaMedico->ragione_sociale ?? 'N/A'));
            $this->info('Remind già inviato: ' . ($appuntamento->remind_send ? 'Sì' : 'No'));

            if (!$preventivo || !$preventivo->email_paziente) {
                $this->error('Email paziente mancante, skip');
                continue;
            }

            if ($this->confirm('Vuoi inviare la email di remind per questo appuntamento?', true)) {
                try {
                    $nomeStudio = $anagraficaMedico->ragione_sociale ?? 'Studio Medico';

                    Notification::route('mail', $preventivo->email_paziente)
                        ->notify(new AppuntamentoRemindPazienteNotification(
                            $appuntamento,
                            $preventivo->nome_paziente ?? '',
                            $preventivo->cognome_paziente ?? '',
                            $nomeStudio
                        ));

                    $this->info('✓ Email inviata con successo!');

                    // NON aggiorniamo remind_send nel test
                    $this->warn('(Nota: remind_send NON è stato aggiornato perché questo è un test)');

                } catch (\Exception $e) {
                    $this->error('✗ Errore invio email: ' . $e->getMessage());
                }
            } else {
                $this->info('Skip appuntamento');
            }
        }

        $this->info('');
        $this->info('=== TEST COMPLETATO ===');
        return 0;
    }
}
