<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, string $name): User
    {
        return User::factory()->create(['name' => $name, 'email' => fake()->unique()->safeEmail(), 'role' => $role, 'status' => 'approved']);
    }

    private function conversation(User $first, User $second): ChatConversation
    {
        $ids = [$first->id, $second->id];
        sort($ids, SORT_NUMERIC);
        $conversation = ChatConversation::create(['pair_key' => hash('sha256', implode(':', $ids)), 'created_by' => $first->id]);
        $conversation->participants()->attach($ids);
        return $conversation;
    }

    public function test_transformer_can_start_a_private_conversation_with_a_distributor_and_send_a_message(): void
    {
        $transformer = $this->account('transformateur', 'Transformateur Test');
        $distributor = $this->account('distributeur', 'Distributeur Test');

        $response = $this->actingAs($transformer)->postJson(route('messaging.conversations.store'), ['user_id' => $distributor->id])->assertOk();
        $conversationId = $response->json('conversation_id');
        $this->assertDatabaseCount('chat_conversations', 1);
        $this->assertDatabaseHas('chat_conversation_user', ['conversation_id' => $conversationId, 'user_id' => $transformer->id]);
        $this->assertDatabaseHas('chat_conversation_user', ['conversation_id' => $conversationId, 'user_id' => $distributor->id]);

        $clientUuid = fake()->uuid();
        $this->postJson(route('messaging.messages.store', $conversationId), ['body' => 'Bonjour pour votre livraison.', 'client_uuid' => $clientUuid, 'sender_id' => $distributor->id])
            ->assertCreated()->assertJsonPath('message.sender_id', $transformer->id)->assertJsonPath('message.status', 'sent');
        $this->assertDatabaseHas('chat_messages', ['conversation_id' => $conversationId, 'sender_id' => $transformer->id, 'body' => 'Bonjour pour votre livraison.', 'client_uuid' => $clientUuid]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $distributor->id, 'type' => NewChatMessage::class]);

        $this->actingAs($distributor)->getJson(route('messaging.messages.index', $conversationId))->assertOk();
        $this->assertNotNull($distributor->notifications()->where('type', NewChatMessage::class)->firstOrFail()->read_at);
    }

    public function test_duplicate_send_request_is_idempotent_and_does_not_duplicate_notifications(): void
    {
        $sender = $this->account('transformateur', 'Atelier');
        $recipient = $this->account('distributeur', 'Marché');
        $conversation = $this->conversation($sender, $recipient);
        $payload = ['body' => 'Commande prête', 'client_uuid' => fake()->uuid()];

        $this->actingAs($sender)->postJson(route('messaging.messages.store', $conversation->id), $payload)->assertCreated();
        $this->postJson(route('messaging.messages.store', $conversation->id), $payload)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_messages_become_delivered_when_the_recipient_opens_inbox_and_read_when_opening_thread(): void
    {
        $sender = $this->account('transformateur', 'Atelier');
        $recipient = $this->account('distributeur', 'Marché');
        $conversation = $this->conversation($sender, $recipient);
        $message = $conversation->messages()->create(['sender_id' => $sender->id, 'client_uuid' => fake()->uuid(), 'body' => 'Votre commande est prête.']);

        $this->actingAs($recipient)->getJson(route('messaging.state'))->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertNotNull($message->fresh()->delivered_at);
        $this->getJson(route('messaging.messages.index', $conversation->id))->assertOk()->assertJsonPath('messages.0.status', null);
        $this->assertNotNull($message->fresh()->read_at);
        $this->actingAs($sender)->getJson(route('messaging.messages.index', $conversation->id))
            ->assertOk()->assertJsonPath('messages.0.status', 'read');
    }

    public function test_a_nonparticipant_cannot_read_or_send_to_a_conversation(): void
    {
        $first = $this->account('transformateur', 'Atelier');
        $second = $this->account('distributeur', 'Marché');
        $stranger = $this->account('transformateur', 'Autre atelier');
        $conversation = $this->conversation($first, $second);

        $this->actingAs($stranger)->getJson(route('messaging.messages.index', $conversation->id))->assertNotFound();
        $this->postJson(route('messaging.messages.store', $conversation->id), ['body' => 'Intrusion', 'client_uuid' => fake()->uuid()])->assertNotFound();
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_only_transformers_and_distributors_can_open_messaging_and_start_cross_role_chats(): void
    {
        $producer = $this->account('producteur', 'Producteur');
        $distributor = $this->account('distributeur', 'Distributeur');
        $transformer = $this->account('transformateur', 'Transformateur');

        $this->actingAs($producer)->get(route('messaging.index'))->assertForbidden();
        $this->actingAs($transformer)->postJson(route('messaging.conversations.store'), ['user_id' => $producer->id])->assertUnprocessable();
        $this->actingAs($transformer)->postJson(route('messaging.conversations.store'), ['user_id' => $distributor->id])->assertOk();
        $this->actingAs($distributor)->get(route('messaging.index'))->assertOk()->assertSee('Messagerie');
    }

    public function test_search_returns_only_permitted_contacts_and_accessible_message_matches(): void
    {
        $transformer = $this->account('transformateur', 'Atelier');
        $contact = $this->account('distributeur', 'Marché Bio');
        $unrelated = $this->account('producteur', 'Marché Bio Producteur');
        $conversation = $this->conversation($transformer, $contact);
        $conversation->messages()->create(['sender_id' => $contact->id, 'client_uuid' => fake()->uuid(), 'body' => 'Les tomates sont livrées demain.']);

        $response = $this->actingAs($transformer)->getJson(route('messaging.state', ['q' => 'tomates']))->assertOk();
        $response->assertJsonPath('message_results.0.participant_name', $contact->name)->assertJsonPath('message_results.0.body', 'Les tomates sont livrées demain.');
        $response = $this->getJson(route('messaging.state', ['q' => 'Marché']))->assertOk();
        $response->assertJsonFragment(['id' => $contact->id]);
        $this->assertNotContains($unrelated->id, collect($response->json('contacts'))->pluck('id')->all());
    }

    public function test_messaging_directory_lists_approved_opposite_role_accounts(): void
    {
        $transformer = $this->account('transformateur', 'Atelier');
        $distributor = $this->account('distributeur', 'Distributeur visible');
        $pendingDistributor = User::factory()->create(['role' => 'distributeur', 'status' => 'pending']);
        $producer = $this->account('producteur', 'Producteur exclu');

        $response = $this->actingAs($transformer)->getJson(route('messaging.state'))->assertOk();
        $response->assertJsonPath('directory_contacts.0.id', $distributor->id);
        $this->assertNotContains($pendingDistributor->id, collect($response->json('directory_contacts'))->pluck('id')->all());
        $this->assertNotContains($producer->id, collect($response->json('directory_contacts'))->pluck('id')->all());

        $response = $this->actingAs($distributor)->getJson(route('messaging.state'))->assertOk();
        $response->assertJsonPath('directory_contacts.0.id', $transformer->id);
    }

    public function test_validation_and_presence_heartbeat_are_applied(): void
    {
        $transformer = $this->account('transformateur', 'Atelier');
        $distributor = $this->account('distributeur', 'Marché');
        $conversation = $this->conversation($transformer, $distributor);

        $this->actingAs($transformer)->postJson(route('messaging.messages.store', $conversation->id), ['body' => " \n ", 'client_uuid' => fake()->uuid()])->assertUnprocessable();
        $this->postJson(route('messaging.messages.store', $conversation->id), ['body' => str_repeat('x', 5001), 'client_uuid' => fake()->uuid()])->assertUnprocessable();
        $this->postJson(route('messaging.presence'))->assertOk()->assertJsonPath('status', 'online');
        $this->assertTrue(Cache::has('messaging:presence:'.$transformer->id));
        $this->assertNotNull($transformer->fresh()->last_active_at);
    }

    public function test_conversation_list_includes_unread_count_and_the_inbox_can_be_reopened(): void
    {
        $transformer = $this->account('transformateur', 'Atelier');
        $distributor = $this->account('distributeur', 'Marché');
        $conversation = $this->conversation($transformer, $distributor);
        $conversation->messages()->create(['sender_id' => $distributor->id, 'client_uuid' => fake()->uuid(), 'body' => 'Question sur le lot.']);

        $this->actingAs($transformer)->getJson(route('messaging.state'))->assertOk()
            ->assertJsonPath('unread_count', 1)->assertJsonPath('conversations.0.unread_count', 1)
            ->assertJsonPath('conversations.0.participant.name', $distributor->name);
        $this->assertSame(1, ChatMessage::unreadCountFor($transformer->id));
    }

    public function test_polling_marks_only_messages_returned_in_the_current_batch_as_read(): void
    {
        $sender = $this->account('transformateur', 'Atelier');
        $recipient = $this->account('distributeur', 'Marché');
        $conversation = $this->conversation($sender, $recipient);
        $cursorMessage = $conversation->messages()->create(['sender_id' => $sender->id, 'client_uuid' => fake()->uuid(), 'body' => 'Début']);
        $incoming = collect(range(1, 102))->map(fn (int $index) => $conversation->messages()->create([
            'sender_id' => $sender->id,
            'client_uuid' => fake()->uuid(),
            'body' => 'Message '.$index,
        ]));

        $this->actingAs($recipient)->getJson(route('messaging.messages.index', ['conversation' => $conversation->id, 'after_id' => $cursorMessage->id]))
            ->assertOk()->assertJsonCount(100, 'messages');

        $this->assertSame(0, $incoming->take(100)->filter(fn (ChatMessage $message) => $message->fresh()->read_at === null)->count());
        $this->assertSame(2, $incoming->skip(100)->filter(fn (ChatMessage $message) => $message->fresh()->read_at === null)->count());
    }
}
