<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'client_uuid', 'body', 'delivered_at', 'read_at'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public static function unreadCountFor(int $userId): int
    {
        return static::query()->where('sender_id', '!=', $userId)->whereNull('read_at')
            ->whereHas('conversation.participants', fn ($query) => $query->where('users.id', $userId))
            ->count();
    }
}
