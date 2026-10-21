<?php

namespace Tests\Feature;

use App\Mail\HarvestPlanReminderMail;
use App\Models\Farm;
use App\Models\Product;
use App\Models\User;
use App\Notifications\HarvestPlanNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HarvestPlanTest extends TestCase
{
    use RefreshDatabase;

    private function farmAndProduct(User $owner, string $name = 'Ferme test'): array
    {
        $farm = Farm::create(['user_id' => $owner->id, 'name' => $name, 'reference' => 'FARM-'.uniqid(), 'location' => 'Tunis', 'country' => 'Tunisie', 'status' => 'active']);
        $product = Product::create(['user_id' => $owner->id, 'farm_id' => $farm->id, 'name' => 'Tomates', 'reference' => 'PROD-'.uniqid(), 'category' => 'Légumes', 'unit' => 'kg', 'status' => 'published']);

        return [$farm, $product];
    }

    public function test_producer_can_create_plan_for_their_own_farm_and_product(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        [$farm, $product] = $this->farmAndProduct($producer);
        $this->actingAs($producer)->post(route('producer.harvest-plans.store'), [
            'farm_id' => $farm->id, 'product_id' => $product->id, 'starts_at' => today()->addDays(3)->toDateString(),
            'ends_at' => today()->addDays(4)->toDateString(), 'estimated_quantity' => 120.5, 'unit' => 'kg', 'reminder_days' => 3, 'notes' => 'Parcelle nord',
        ])->assertRedirect();
        $this->assertDatabaseHas('harvest_plans', ['user_id' => $producer->id, 'farm_id' => $farm->id, 'product_id' => $product->id, 'status' => 'planned']);
        $this->assertDatabaseHas('harvest_plan_histories', ['event' => 'created']);
    }

    public function test_producer_cannot_create_a_plan_using_another_producers_product_or_farm(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $other = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        [$farm] = $this->farmAndProduct($producer);
        [$otherFarm, $otherProduct] = $this->farmAndProduct($other);
        $payload = ['farm_id' => $farm->id, 'product_id' => $otherProduct->id, 'starts_at' => today()->toDateString(), 'ends_at' => today()->toDateString(), 'estimated_quantity' => 10, 'unit' => 'kg', 'reminder_days' => 1];
        $this->actingAs($producer)->from('/producer/harvest-plans/create')->post(route('producer.harvest-plans.store'), $payload)->assertSessionHasErrors('product_id');
        $payload['farm_id'] = $otherFarm->id;
        $this->actingAs($producer)->from('/producer/harvest-plans/create')->post(route('producer.harvest-plans.store'), $payload)->assertSessionHasErrors('farm_id');
        $this->assertDatabaseCount('harvest_plans', 0);
    }

    public function test_producer_cannot_read_another_producers_plan_and_other_roles_cannot_open_module(): void
    {
        $owner = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $other = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        [$farm, $product] = $this->farmAndProduct($owner);
        $plan = $owner->harvestPlans()->create(['farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-ISOLATION', 'starts_at' => today(), 'ends_at' => today(), 'estimated_quantity' => 1, 'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 1]);
        $this->actingAs($other)->get(route('producer.harvest-plans.show', $plan))->assertNotFound();
        $transformer = User::factory()->create(['role' => 'transformateur', 'status' => 'approved']);
        $this->actingAs($transformer)->get(route('producer.harvest-plans.index'))->assertForbidden();
    }

    public function test_dates_and_quantity_are_validated_and_status_transitions_are_controlled(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        [$farm, $product] = $this->farmAndProduct($producer);
        $payload = ['farm_id' => $farm->id, 'product_id' => $product->id, 'starts_at' => today()->addDays(2)->toDateString(), 'ends_at' => today()->toDateString(), 'estimated_quantity' => 0, 'unit' => 'kg', 'reminder_days' => 1];
        $this->actingAs($producer)->from('/producer/harvest-plans/create')->post(route('producer.harvest-plans.store'), $payload)->assertSessionHasErrors(['ends_at', 'estimated_quantity']);
        $plan = $producer->harvestPlans()->create(['farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-STATE', 'starts_at' => today(), 'ends_at' => today()->addDay(), 'estimated_quantity' => 10, 'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 1]);
        $this->patch(route('producer.harvest-plans.status', $plan), ['status' => 'completed'])->assertStatus(422);
        $this->patch(route('producer.harvest-plans.status', $plan), ['status' => 'in_progress'])->assertRedirect();
        $this->patch(route('producer.harvest-plans.status', $plan), ['status' => 'completed'])->assertRedirect();
        $this->assertDatabaseHas('harvest_plans', ['id' => $plan->id, 'status' => 'completed']);
        $this->assertDatabaseHas('harvest_plan_histories', ['harvest_plan_id' => $plan->id, 'event' => 'status_changed']);
    }

    public function test_producer_can_update_and_delete_an_owned_plan(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        [$farm, $product] = $this->farmAndProduct($producer);
        $plan = $producer->harvestPlans()->create(['farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-CRUD', 'starts_at' => today()->addDays(3), 'ends_at' => today()->addDays(4), 'estimated_quantity' => 10, 'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 1]);
        $this->actingAs($producer)->put(route('producer.harvest-plans.update', $plan), ['farm_id' => $farm->id, 'product_id' => $product->id, 'starts_at' => today()->addDays(5)->toDateString(), 'ends_at' => today()->addDays(6)->toDateString(), 'estimated_quantity' => 25, 'unit' => 'caisses', 'reminder_days' => 5, 'notes' => 'Nouvelle estimation'])->assertRedirect();
        $this->assertDatabaseHas('harvest_plans', ['id' => $plan->id, 'estimated_quantity' => 25, 'unit' => 'caisses']);
        $this->delete(route('producer.harvest-plans.destroy', $plan))->assertRedirect(route('producer.harvest-plans.index'));
        $this->assertSoftDeleted('harvest_plans', ['id' => $plan->id]);
    }

    public function test_calendar_events_are_filtered_and_owned_by_the_authenticated_producer(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $other = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        [$farm, $product] = $this->farmAndProduct($producer);
        [$otherFarm, $otherProduct] = $this->farmAndProduct($other);
        foreach ([[$producer, $farm, $product, 'REC-OWN'], [$other, $otherFarm, $otherProduct, 'REC-OTHER']] as [$owner, $planFarm, $planProduct, $reference]) {
            $owner->harvestPlans()->create(['farm_id' => $planFarm->id, 'product_id' => $planProduct->id, 'reference' => $reference, 'starts_at' => today(), 'ends_at' => today(), 'estimated_quantity' => 1, 'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 1]);
        }
        $this->actingAs($producer)->getJson(route('producer.harvest-plans.events', ['start' => today()->toDateString(), 'end' => today()->addDay()->toDateString()]))
            ->assertOk()->assertJsonCount(1)->assertJsonFragment(['title' => 'Tomates · 1.000 kg']);
    }

    public function test_calendar_and_creation_form_render_for_a_producer(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        [$farm, $product] = $this->farmAndProduct($producer);
        $this->actingAs($producer)->get(route('producer.harvest-plans.index'))->assertOk()->assertSee('Planification des récoltes')->assertSee('harvest-calendar');
        $this->get(route('producer.harvest-plans.create'))->assertOk()->assertSee('Planifier une récolte')->assertSee($farm->name)->assertSee($product->name);
    }

    public function test_reminders_due_today_and_overdue_notifications_are_not_duplicated(): void
    {
        Mail::fake();
        $this->travelTo(today()->setTime(8, 0));
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        [$farm, $product] = $this->farmAndProduct($producer);
        $todayPlan = $producer->harvestPlans()->create(['farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-TODAY', 'starts_at' => today(), 'ends_at' => today(), 'estimated_quantity' => 10, 'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 1]);
        $reminderPlan = $producer->harvestPlans()->create(['farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-REMINDER', 'starts_at' => today()->addDays(2), 'ends_at' => today()->addDays(2), 'estimated_quantity' => 8, 'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 2]);
        $latePlan = $producer->harvestPlans()->create(['farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-LATE', 'starts_at' => today()->subDays(3), 'ends_at' => today()->subDay(), 'estimated_quantity' => 5, 'unit' => 'kg', 'status' => 'in_progress', 'reminder_days' => 1]);
        $this->artisan('nutritrace:harvest-reminders')->assertSuccessful();
        $this->artisan('nutritrace:harvest-reminders')->assertSuccessful();
        $this->assertSame(3, $producer->notifications()->where('type', HarvestPlanNotification::class)->count());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $producer->id, 'data->harvest_plan_id' => $todayPlan->id, 'data->event_key' => 'due_today']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $producer->id, 'data->harvest_plan_id' => $reminderPlan->id, 'data->event_key' => 'reminder_2']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $producer->id, 'data->harvest_plan_id' => $latePlan->id, 'data->event_key' => 'overdue']);
        Mail::assertSent(HarvestPlanReminderMail::class, 2);
        $this->assertNotNull($reminderPlan->fresh()->reminder_email_sent_at);
        $this->assertNull($latePlan->fresh()->reminder_email_sent_at);
    }

    public function test_disabling_reminder_sends_no_email_and_rescheduling_allows_new_reminder_date(): void
    {
        Mail::fake();
        $this->travelTo(today()->setTime(7, 0));
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        [$farm, $product] = $this->farmAndProduct($producer);
        $disabled = $producer->harvestPlans()->create([
            'farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-MAIL-OFF',
            'starts_at' => today(), 'ends_at' => today()->addDays(2), 'estimated_quantity' => 10,
            'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 0,
        ]);
        $plan = $producer->harvestPlans()->create([
            'farm_id' => $farm->id, 'product_id' => $product->id, 'reference' => 'REC-MAIL-ON',
            'starts_at' => today(), 'ends_at' => today()->addDays(2), 'estimated_quantity' => 8,
            'unit' => 'kg', 'status' => 'planned', 'reminder_days' => 2, 'notes' => 'Vérifier la parcelle nord.',
        ]);

        $this->artisan('nutritrace:harvest-reminders')->assertSuccessful();
        Mail::assertSent(HarvestPlanReminderMail::class, 1);
        Mail::assertSent(HarvestPlanReminderMail::class, function ($mail) use ($producer, $plan): bool {
            $html = $mail->render();

            return $mail->hasTo($producer->email)
                && $mail->plan->is($plan)
                && $mail->envelope()->subject === 'Rappel de votre événement — NutriTrace'
                && str_contains($html, 'Tomates')
                && str_contains($html, 'Vérifier la parcelle nord.')
                && str_contains($html, route('producer.harvest-plans.show', $plan));
        });
        $this->assertNull($disabled->fresh()->reminder_email_sent_at);
        $this->assertNotNull($plan->fresh()->reminder_email_sent_at);

        $plan->update(['ends_at' => today()->addDay(), 'reminder_days' => 1]);
        $this->assertNull($plan->fresh()->reminder_email_sent_at);
        $this->artisan('nutritrace:harvest-reminders')->assertSuccessful();
        Mail::assertSent(HarvestPlanReminderMail::class, 2);
    }
}
