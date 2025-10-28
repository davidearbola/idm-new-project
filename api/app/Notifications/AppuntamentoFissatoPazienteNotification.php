<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appuntamento;
use Carbon\Carbon;

class AppuntamentoFissatoPazienteNotification extends Notification
{
    use Queueable;

    public $appuntamento;
    public $nomePaziente;
    public $cognomePaziente;
    public $nomeStudio;

    public function __construct(
        Appuntamento $appuntamento,
        string $nomePaziente,
        string $cognomePaziente,
        string $nomeStudio
    ) {
        $this->appuntamento = $appuntamento;
        $this->nomePaziente = $nomePaziente;
        $this->cognomePaziente = $cognomePaziente;
        $this->nomeStudio = $nomeStudio;
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

        // Per ora link alla home, in futuro sarà Google Maps
        $url = env('FRONTEND_URL');

        return (new MailMessage)
            ->subject('Conferma appuntamento Il Dentista Migliore')
            ->greeting('Gentile ' . $nomeCompleto . ',')
            ->line("è stato fissato l'appuntamento dal dentista per il giorno " . $dataFormatted . " alle ore " . $oraFormatted . " presso lo studio " . $this->nomeStudio . ".")
            ->line('Indirizzo Studio: ............')
            ->line('Per raggiungere lo studio seguire le indicazioni su Google Maps cliccando qui sotto.')
            ->action('Visualizza indicazioni', $url)
            ->salutation('Cordiali saluti');
    }
}
