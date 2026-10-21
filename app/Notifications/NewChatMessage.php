<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewChatMessage extends Notification
{
    use Queueable;

    public function __construct(public ChatMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'chat_message',
            'message' => 'Nouveau message de '.$this->message->sender->name,
            'preview' => mb_strimwidth($this->message->body, 0, 140, '…'),
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->name,
            'conversation_id' => $this->message->conversation_id,
            'chat_message_id' => $this->message->id,
            'url' => route('messaging.index', ['conversation' => $this->message->conversation_id]),
        ];
    }
}
