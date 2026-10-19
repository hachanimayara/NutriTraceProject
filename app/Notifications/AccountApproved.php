<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountApproved extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre compte NutriTrace est approuvé')
            ->greeting('Bonjour '.$notifiable->name)
            ->line('Votre demande de création de compte a été approuvée par notre équipe.')
            ->line('Vous pouvez maintenant vous connecter à votre espace NutriTrace.')
            ->action('Se connecter', route('login'));
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => 'Votre compte NutriTrace a été approuvé.'];
    }
}
