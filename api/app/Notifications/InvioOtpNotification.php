<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvioOtpNotification extends Notification
{
    use Queueable;

    public $otpCode;
    public $expiresInMinutes;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otpCode, int $expiresInMinutes = 10)
    {
        $this->otpCode = $otpCode;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Il tuo codice di accesso')
            ->greeting('Ciao!')
            ->line('Hai richiesto l\'accesso alle tue proposte.')
            ->line('Il tuo codice di verifica è:')
            ->line('**' . $this->otpCode . '**')
            ->line('Questo codice scadrà tra ' . $this->expiresInMinutes . ' minuti.')
            ->line('Se non hai richiesto tu questo codice, puoi ignorare questa email.')
            ->salutation('Cordiali saluti,');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'otp_code' => $this->otpCode,
            'expires_in_minutes' => $this->expiresInMinutes,
        ];
    }
}
