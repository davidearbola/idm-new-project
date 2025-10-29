<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PreventivoPaziente;
use Illuminate\Support\Str;

class GenerateAccessTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preventivi:generate-tokens {--force : Sovrascrivi i token esistenti}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera access_token per i preventivi esistenti che non ne hanno uno';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔐 Generazione access_token per preventivi esistenti...');
        $this->newLine();

        $force = $this->option('force');

        // Query per preventivi senza token o tutti se --force
        $query = PreventivoPaziente::query();

        if (!$force) {
            $query->whereNull('access_token');
        }

        $preventivi = $query->get();

        if ($preventivi->isEmpty()) {
            $this->info('✅ Tutti i preventivi hanno già un access_token!');
            return Command::SUCCESS;
        }

        $this->info("Trovati {$preventivi->count()} preventivi da processare...");
        $this->newLine();

        $bar = $this->output->createProgressBar($preventivi->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;

        foreach ($preventivi as $preventivo) {
            if (!$force && $preventivo->access_token) {
                $skipped++;
            } else {
                $preventivo->access_token = (string) Str::uuid();
                $preventivo->save();
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Operazione completata!");
        $this->table(
            ['Statistica', 'Valore'],
            [
                ['Preventivi aggiornati', $updated],
                ['Preventivi saltati', $skipped],
                ['Totale processati', $preventivi->count()],
            ]
        );

        return Command::SUCCESS;
    }
}
