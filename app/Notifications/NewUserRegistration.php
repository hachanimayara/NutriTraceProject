<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegistration extends Notification
{
    use Queueable;

    public function __construct(public User $newUser) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Nouvelle demande de compte NutriTrace')->greeting('Bonjour '.$notifiable->name)->line($this->newUser->name.' demande l’accès en tant que '.$this->newUser->role.'.')->action('Examiner la demande', route('admin.users.show', $this->newUser));
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => 'Nouvelle demande de compte', 'user_id' => $this->newUser->id, 'name' => $this->newUser->name, 'email' => $this->newUser->email, 'role' => $this->newUser->role];
    }
}
