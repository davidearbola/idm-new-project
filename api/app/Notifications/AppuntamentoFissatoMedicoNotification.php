<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Appuntamento;
use Carbon\Carbon;

class AppuntamentoFissatoMedicoNotification extends Notification
{
    use Queueable;

    public $appuntamento;

    public function __construct(Appuntamento $appuntamento)
    {
        $this->appuntamento = $appuntamento;
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

        // Link alla pagina degli appuntamenti del medico
        $url = env('FRONTEND_URL') . '/dashboard/appuntamenti-medico';

        return (new MailMessage)
            ->subject('Nuovo appuntamento Il Dentista Migliore')
            ->greeting('Gentile Professionista,')
            ->line('Il Dentista Migliore le ha fissato un appuntamento presso il suo Studio per il giorno ' . $dataFormatted . ' alle ore ' . $oraFormatted . '.')
            ->line('Consulti la sua lista appuntamenti accedendo con le sue credenziali cliccando qui sotto.')
            ->action('Visualizza lista appuntamenti', $url)
            ->salutation('Buon lavoro');
    }
}
