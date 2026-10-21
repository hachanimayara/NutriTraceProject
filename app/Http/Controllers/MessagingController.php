<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessagingController extends Controller
{
    private const PRESENCE_TTL = 90;

    public function index()
    {
        return view('messaging.index');
    }

    public function state(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $user = $request->user();
        $this->touchPresence($user);
        $term = trim($filters['q'] ?? '');
        $conversationIds = $this->ownedConversations($user)->select('chat_conversations.id');

        // A message is delivered once the recipient's active inbox has picked it up.
        ChatMessage::query()->whereIn('conversation_id', (clone $conversationIds))
            ->where('sender_id', '!=', $user->id)->whereNull('delivered_at')
            ->update(['delivered_at' => now(), 'updated_at' => now()]);

        $conversations = $this->ownedConversations($user)
            ->with(['participants:id,name,role,profile_photo_path,last_active_at', 'lastMessage.sender:id,name'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $query->where('sender_id', '!=', $user->id)->whereNull('read_at')])
            ->when($term !== '', function (Builder $query) use ($term, $user) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(function (Builder $query) use ($like, $user) {
                    $query->whereHas('participants', fn (Builder $participants) => $participants->where('users.id', '!=', $user->id)->where('name', 'like', $like))
                        ->orWhereHas('messages', fn (Builder $messages) => $messages->where('body', 'like', $like));
                });
            })
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->limit(50)->get();

        $conversations = $conversations->map(function (ChatConversation $conversation) use ($user) {
            $other = $conversation->participants->first(fn (User $participant) => $participant->id !== $user->id);
            $last = $conversation->lastMessage;

            return [
                'id' => $conversation->id,
                'participant' => $other ? $this->userPayload($other) : null,
                'last_message' => $last ? [
                    'id' => $last->id,
                    'body' => $last->body,
                    'sender_id' => $last->sender_id,
                    'created_at' => $last->created_at?->toIso8601String(),
                    'status' => $last->sender_id === $user->id ? ($last->read_at ? 'read' : ($last->delivered_at ? 'delivered' : 'sent')) : null,
                ] : null,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'unread_count' => (int) $conversation->unread_count,
            ];
        })->filter(fn ($conversation) => $conversation['participant'] !== null)->values();

        $otherRole = $user->role === 'transformateur' ? 'distributeur' : 'transformateur';
        $directoryContacts = User::query()->where('role', $otherRole)->where('status', 'approved')
            ->orderBy('name')->limit(50)
            ->get(['id', 'name', 'role', 'organization', 'profile_photo_path', 'last_active_at'])
            ->map(fn (User $contact) => $this->userPayload($contact));

        $contacts = collect();
        $messageResults = collect();
        if (mb_strlen($term) >= 2) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $contacts = User::query()->where('role', $otherRole)->where('status', 'approved')
                ->where(function (Builder $query) use ($like) {
                    $query->where('name', 'like', $like)->orWhere('organization', 'like', $like);
                })
                ->orderBy('name')->limit(10)->get(['id', 'name', 'role', 'organization', 'profile_photo_path', 'last_active_at'])
                ->map(fn (User $contact) => $this->userPayload($contact));

            $messageResults = ChatMessage::query()->whereIn('conversation_id', (clone $conversationIds))
                ->where('body', 'like', $like)->with(['conversation.participants:id,name,role', 'sender:id,name'])
                ->latest('id')->limit(12)->get()->map(function (ChatMessage $message) use ($user) {
                    $other = $message->conversation->participants->first(fn (User $participant) => $participant->id !== $user->id);

                    return [
                        'id' => $message->id,
                        'conversation_id' => $message->conversation_id,
                        'sender_name' => $message->sender->name,
                        'participant_name' => $other?->name,
                        'body' => mb_strimwidth($message->body, 0, 150, '…'),
                        'created_at' => $message->created_at?->toIso8601String(),
                    ];
                });
        }

        return response()->json([
            'conversations' => $conversations,
            'directory_contacts' => $directoryContacts,
            'contacts' => $contacts,
            'message_results' => $messageResults,
            'unread_count' => ChatMessage::unreadCountFor($user->id),
            'polling' => ['messages_seconds' => 3, 'inbox_seconds' => 10, 'presence_seconds' => 30],
        ]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $user = $request->user();
        $otherRole = $user->role === 'transformateur' ? 'distributeur' : 'transformateur';
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', $otherRole)->where('status', 'approved')->whereNull('deleted_at')],
        ]);
        abort_if((int) $data['user_id'] === $user->id, 422);

        $ids = [$user->id, (int) $data['user_id']];
        sort($ids, SORT_NUMERIC);
        $conversation = DB::transaction(function () use ($user, $ids) {
            $conversation = ChatConversation::query()->firstOrCreate(
                ['pair_key' => hash('sha256', implode(':', $ids))],
                ['created_by' => $user->id],
            );
            $conversation->participants()->syncWithoutDetaching($ids);

            return $conversation;
        });

        return response()->json(['conversation_id' => $conversation->id], 200);
    }

    public function messages(Request $request, int $conversation): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->ownedConversations($user)->findOrFail($conversation);
        $cursor = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if (isset($cursor['before_id'], $cursor['after_id'])) {
            throw ValidationException::withMessages(['after_id' => 'Choisissez un seul curseur de pagination.']);
        }

        $query = $conversation->messages()->with('sender:id,name,role,profile_photo_path');
        $isPolling = isset($cursor['after_id']);
        if ($isPolling) {
            $query->where('id', '>', $cursor['after_id'])->oldest('id')->limit(100);
        } elseif (isset($cursor['before_id'])) {
            $query->where('id', '<', $cursor['before_id'])->latest('id')->limit(40);
        } else {
            $query->latest('id')->limit(40);
        }

        $messages = $query->get();
        if (! $isPolling) $messages = $messages->reverse()->values();
        if (! $isPolling && $messages->isNotEmpty()) {
            $incomingIds = $messages->where('sender_id', '!=', $user->id)->pluck('id');
            if ($incomingIds->isNotEmpty()) {
                ChatMessage::query()->whereIn('id', $incomingIds)->whereNull('delivered_at')
                    ->update(['delivered_at' => now(), 'updated_at' => now()]);
                ChatMessage::query()->whereIn('id', $incomingIds)->whereNull('read_at')
                    ->update(['read_at' => now(), 'updated_at' => now()]);
                $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
            }
            $user->notifications()->where('type', NewChatMessage::class)->whereNull('read_at')
                ->whereIn('data->chat_message_id', $incomingIds)->update(['read_at' => now(), 'updated_at' => now()]);
            $messages = $messages->fresh(['sender:id,name,role,profile_photo_path']);
        }

        if ($isPolling && $messages->isNotEmpty()) {
            $incomingIds = $messages->where('sender_id', '!=', $user->id)->pluck('id');
            if ($incomingIds->isNotEmpty()) {
                ChatMessage::query()->whereIn('id', $incomingIds)->whereNull('delivered_at')
                    ->update(['delivered_at' => now(), 'updated_at' => now()]);
                ChatMessage::query()->whereIn('id', $incomingIds)->whereNull('read_at')
                    ->update(['read_at' => now(), 'updated_at' => now()]);
                $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
            }
            $user->notifications()->where('type', NewChatMessage::class)->whereNull('read_at')
                ->whereIn('data->chat_message_id', $incomingIds)->update(['read_at' => now(), 'updated_at' => now()]);
            $messages = $messages->fresh(['sender:id,name,role,profile_photo_path']);
        }

        $items = $messages->map(fn (ChatMessage $message) => $this->messagePayload($message, $user))->values();
        $lastId = $items->last()['id'] ?? null;
        $hasMore = ! $isPolling && isset($cursor['before_id'])
            ? $conversation->messages()->where('id', '<', $cursor['before_id'])->exists()
            : (! $isPolling && $conversation->messages()->count() > $messages->count());

        return response()->json(['messages' => $items, 'has_more' => $hasMore, 'last_id' => $lastId]);
    }

    public function send(Request $request, int $conversation): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->ownedConversations($user)->findOrFail($conversation);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'client_uuid' => ['required', 'uuid'],
        ]);
        $body = trim($data['body']);
        if ($body === '') return response()->json(['message' => 'Écrivez un message avant de l’envoyer.'], 422);

        $existing = ChatMessage::query()->where('sender_id', $user->id)->where('client_uuid', $data['client_uuid'])->first();
        if ($existing) {
            abort_unless($existing->conversation_id === $conversation->id, 409);
            return response()->json(['message' => $this->messagePayload($existing->load('sender:id,name,role,profile_photo_path'), $user), 'duplicate' => true], 200);
        }

        $message = DB::transaction(function () use ($conversation, $user, $data, $body) {
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'client_uuid' => $data['client_uuid'],
                'body' => $body,
            ]);
            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            $recipient = $conversation->participants()->where('users.id', '!=', $user->id)->first();
            if ($recipient) $recipient->notify(new NewChatMessage($message->load('sender:id,name')));

            return $message->load('sender:id,name,role,profile_photo_path');
        });

        return response()->json(['message' => $this->messagePayload($message, $user), 'duplicate' => false], 201);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $this->touchPresence($request->user());
        return response()->json(['status' => 'online', 'expires_in' => self::PRESENCE_TTL]);
    }

    private function ownedConversations(User $user): Builder
    {
        return ChatConversation::query()->whereHas('participants', fn (Builder $query) => $query->where('users.id', $user->id));
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'organization' => $user->organization,
            'avatar_url' => $user->profile_photo_path ? Storage::disk('public')->url($user->profile_photo_path) : null,
            'online' => Cache::has('messaging:presence:'.$user->id),
            'last_active_at' => $user->last_active_at?->toIso8601String(),
        ];
    }

    private function messagePayload(ChatMessage $message, User $user): array
    {
        return [
            'id' => $message->id,
            'client_uuid' => $message->client_uuid,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender->name,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'status' => $message->sender_id !== $user->id ? null : ($message->read_at ? 'read' : ($message->delivered_at ? 'delivered' : 'sent')),
        ];
    }

    private function touchPresence(User $user): void
    {
        Cache::put('messaging:presence:'.$user->id, true, now()->addSeconds(self::PRESENCE_TTL));
        $activeAt = $user->last_active_at;
        if (! $activeAt || $activeAt->lt(now()->subMinute())) {
            User::query()->whereKey($user->id)->update(['last_active_at' => now()]);
            $user->last_active_at = now();
        }
    }
}
