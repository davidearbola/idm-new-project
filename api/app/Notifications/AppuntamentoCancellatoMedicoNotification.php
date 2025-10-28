<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appuntamento;
use Carbon\Carbon;

class AppuntamentoCancellatoMedicoNotification extends Notification
{
    use Queueable;

    public $appuntamento;
    public $nomePaziente;
    public $cognomePaziente;

    public function __construct(Appuntamento $appuntamento, string $nomePaziente, string $cognomePaziente)
    {
        $this->appuntamento = $appuntamento;
        $this->nomePaziente = $nomePaziente;
        $this->cognomePaziente = $cognomePaziente;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dataAppuntamento = Carbon::parse($this->appuntamento->starting_date_time);
        $dataFormatted = $dataAppuntamento->format('d-m-Y');
        $oraFormatted = $dataAppuntamento->format('H:i');

        $nomeCompleto = $this->nomePaziente . ' ' . $this->cognomePaziente;

        // Link alla pagina di appuntamenti del medico
        $url = env('FRONTEND_URL') . '/dashboard/appuntamenti-medico';

        return (new MailMessage)
            ->subject('Appuntamento disdetto Il Dentista Migliore')
            ->greeting('Gentile Professionista,')
            ->line("Il paziente " . $nomeCompleto . " ha disdetto l'aapuntamento fissato per il giorno " . $dataFormatted . " alle ore " . $oraFormatted . ".")
            ->line('Controlli la lista appuntamenti all\'interno della sua area privata.')
            ->action('Accedi all\'area privata', $url)
            ->salutation('Cordiali saluti');
    }
}
