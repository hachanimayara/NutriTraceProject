<?php

namespace Tests\Feature;

use App\Helpers\MenuHelper;
use App\Models\Distribution;
use App\Models\Farm;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DistributorHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributor_history_lists_owned_distributions_and_applies_database_filters(): void
    {
        [$distributor, $distribution] = $this->makeDistribution('DIST-HIST-1', 'Pommes bio', 'LOT-HIST-1', 'delivered');
        [, $otherDistribution] = $this->makeDistribution('DIST-HIST-2', 'Poires bio', 'LOT-HIST-2', 'in_transit');
        $otherDistribution->user_id = User::factory()->create(['role' => 'distributeur', 'status' => 'approved'])->id;
        $otherDistribution->save();

        $this->actingAs($distributor)->get(route('workspace.history.index', ['q' => 'LOT-HIST-1', 'type' => 'livraison', 'status' => 'delivered']))
            ->assertOk()->assertSee('DIST-HIST-1')->assertSee('Pommes bio')->assertDontSee('DIST-HIST-2')
            ->assertSee('Opérations enregistrées')->assertSee('Livraisons effectuées');
    }

    public function test_history_details_are_limited_to_the_signed_in_distributor(): void
    {
        [$owner, $ownDistribution] = $this->makeDistribution('DIST-OWN', 'Produit à moi', 'LOT-OWN', 'planned');
        [, $foreignDistribution] = $this->makeDistribution('DIST-FOREIGN', 'Produit privé', 'LOT-FOREIGN', 'planned');

        $this->actingAs($owner)->get(route('workspace.history.show', $ownDistribution))->assertOk()->assertSee('DIST-OWN')->assertSee('Produit à moi');
        $this->get(route('workspace.history.show', $foreignDistribution))->assertNotFound();
    }

    public function test_distribution_status_transitions_are_recorded_with_actor_and_shown_in_timeline(): void
    {
        [$distributor, $distribution] = $this->makeDistribution('DIST-TIMELINE-1', 'Olives bio', 'LOT-TIMELINE-1', 'planned');
        $this->actingAs($distributor);

        $distribution->update(['status' => 'in_transit']);

        $this->assertDatabaseHas('distribution_status_histories', [
            'distribution_id' => $distribution->id,
            'from_status' => 'planned',
            'to_status' => 'in_transit',
            'changed_by' => $distributor->id,
        ]);
        $this->get(route('workspace.history.show', $distribution))
            ->assertOk()->assertSee('Parcours de la commande')->assertSee('Commande confirmée')
            ->assertSee('Cette étape n’est pas historisée')->assertSee('Étape actuelle')
            ->assertSee('Changements de statut enregistrés')->assertSee($distributor->name);
    }

    public function test_cancelled_distribution_displays_cancellation_without_inventing_a_reason(): void
    {
        [$distributor, $distribution] = $this->makeDistribution('DIST-TIMELINE-CANCEL', 'Produit annulé', 'LOT-TIMELINE-CANCEL', 'cancelled');

        $this->actingAs($distributor)->get(route('workspace.history.show', $distribution))
            ->assertOk()->assertSee('Cette distribution a été annulée.')
            ->assertSee('Le motif d’annulation n’est pas conservé');
    }

    public function test_history_routes_are_distributor_only_and_menu_item_is_role_scoped(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer)->get(route('workspace.history.index'))->assertForbidden();
        $this->assertFalse(collect(MenuHelper::getMenuGroups()[0]['items'])->contains(fn ($item) => $item['name'] === 'Historique'));

        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $this->actingAs($distributor);
        $this->assertTrue(collect(MenuHelper::getMenuGroups()[0]['items'])->contains(fn ($item) => $item['name'] === 'Historique' && $item['path'] === '/workspace/history'));
        $this->get(route('workspace.history.index'))->assertOk();
    }

    public function test_history_validates_date_filter_order(): void
    {
        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $this->actingAs($distributor)->get(route('workspace.history.index', ['from' => '2026-10-20', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_history_paginates_and_filters_by_operation_date(): void
    {
        [$distributor] = $this->makeDistribution('DIST-PAGE-00', 'Produit pagination 0', 'LOT-PAGE-00', 'in_transit');
        for ($index = 1; $index <= 12; $index++) {
            $this->makeDistribution(sprintf('DIST-PAGE-%02d', $index), 'Produit pagination '.$index, sprintf('LOT-PAGE-%02d', $index), 'in_transit', $distributor);
        }

        $this->actingAs($distributor)->get(route('workspace.history.index', ['from' => today()->toDateString(), 'to' => today()->toDateString(), 'page' => 2]))
            ->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 13 && $records->count() === 1 && $records->currentPage() === 2)
            ->assertSee('DIST-PAGE-00');
    }

    private function makeDistribution(string $reference, string $productName, string $lotCode, string $status, ?User $owner = null): array
    {
        $distributor = $owner ?? User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme test', 'reference' => 'FARM-'.$reference, 'location' => 'Tunis', 'country' => 'Tunisie']);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => $productName, 'reference' => 'PROD-'.$reference, 'category' => 'Frais', 'unit' => 'kg', 'status' => 'published']);
        $lot = Lot::create(['product_id' => $product->id, 'created_by' => $producer->id, 'code' => $lotCode, 'public_id' => (string) Str::uuid(), 'quantity' => 20, 'unit' => 'kg', 'status' => 'active']);
        $store = Store::create(['user_id' => $distributor->id, 'name' => 'Marché test', 'reference' => 'STORE-'.$reference, 'address' => 'Tunis', 'country' => 'Tunisie']);
        $distribution = Distribution::create([
            'user_id' => $distributor->id, 'lot_id' => $lot->id, 'store_id' => $store->id,
            'reference' => $reference, 'quantity_shipped' => 5, 'quantity_delivered' => $status === 'delivered' ? 5 : null,
            'unit' => 'kg', 'shipped_at' => now(), 'delivered_at' => $status === 'delivered' ? now() : null, 'status' => $status,
        ]);

        return [$distributor, $distribution];
    }
}
