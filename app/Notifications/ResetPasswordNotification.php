<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Réinitialisation du mot de passe NutriTrace')->line('Vous recevez ce message car une réinitialisation a été demandée pour votre compte.')->action('Choisir un nouveau mot de passe', route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]))->line('Ce lien expire dans '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.');
    }
}
