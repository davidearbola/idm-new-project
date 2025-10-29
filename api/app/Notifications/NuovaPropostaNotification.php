<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\PreventivoPaziente;

class NuovaPropostaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $preventivo;
    public $numeroProposte;

    public function __construct(PreventivoPaziente $preventivo, int $numeroProposte)
    {
        $this->preventivo = $preventivo;
        $this->numeroProposte = $numeroProposte;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nomePaziente = $this->preventivo->nome_paziente ?? 'Cliente';

        // Usa l'access_token per creare un link sicuro e permanente
        $url = env('FRONTEND_URL') . '/visualizza-proposte?token=' . $this->preventivo->access_token;

        return (new MailMessage)
            ->subject('Le tue proposte sono pronte!')
            ->greeting('Ciao ' . $nomePaziente . ',')
            ->line('Abbiamo trovato ' . $this->numeroProposte . ' ' . ($this->numeroProposte === 1 ? 'proposta' : 'proposte') . ' per il tuo preventivo!')
            ->line('Clicca sul pulsante qui sotto per visualizzare i dettagli e confrontare le offerte degli studi medici.')
            ->action('Vedi le tue proposte', $url)
            ->line('Grazie per aver scelto la nostra piattaforma!')
            ->salutation('Cordiali saluti,');
    }
}