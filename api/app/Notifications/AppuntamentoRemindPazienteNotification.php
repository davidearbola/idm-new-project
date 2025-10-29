<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appuntamento;
use App\Models\AnagraficaMedico;
use Carbon\Carbon;

class AppuntamentoRemindPazienteNotification extends Notification
{
    use Queueable;

    public $appuntamento;
    public $nomePaziente;
    public $cognomePaziente;
    public $anagraficaMedico;

    public function __construct(
        Appuntamento $appuntamento,
        string $nomePaziente,
        string $cognomePaziente,
        AnagraficaMedico $anagraficaMedico
    ) {
        $this->appuntamento = $appuntamento;
        $this->nomePaziente = $nomePaziente;
        $this->cognomePaziente = $cognomePaziente;
        $this->anagraficaMedico = $anagraficaMedico;
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
        $nomeStudio = $this->anagraficaMedico->ragione_sociale ?? 'Studio Medico';

        // Costruisce l'indirizzo completo
        $indirizzoCompleto = $this->anagraficaMedico->indirizzo;
        if ($this->anagraficaMedico->cap || $this->anagraficaMedico->citta || $this->anagraficaMedico->provincia) {
            $indirizzoCompleto .= ', ' . $this->anagraficaMedico->cap . ' ' .
                                  $this->anagraficaMedico->citta . ' (' .
                                  $this->anagraficaMedico->provincia . ')';
        }

        // Costruisce il link Google Maps
        // Se abbiamo lat/lng, usiamo quelli per maggiore precisione
        if ($this->anagraficaMedico->lat && $this->anagraficaMedico->lng) {
            $googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' .
                           $this->anagraficaMedico->lat . ',' . $this->anagraficaMedico->lng;
        } else {
            // Altrimenti usa l'indirizzo testuale
            $googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($indirizzoCompleto);
        }

        return (new MailMessage)
            ->subject('Promemoria appuntamento')
            ->greeting('Gentile ' . $nomeCompleto . ',')
            ->line('Le ricordiamo che ha un appuntamento presso lo studio ' . $nomeStudio . ' il giorno ' . $dataFormatted . ' alle ore ' . $oraFormatted . '.')
            ->line('Indirizzo studio: ' . $indirizzoCompleto)
            ->line('Per raggiungere lo studio seguire le indicazioni su Google Maps cliccando qui sotto.')
            ->action('Visualizza indicazioni', $googleMapsUrl)
            ->salutation('Cordiali saluti');
    }
}
