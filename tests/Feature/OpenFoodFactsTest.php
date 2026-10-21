<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenFoodFactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transformer_can_fetch_product_by_barcode_and_select_data_for_import(): void
    {
        Cache::flush();
        Http::fake(['world.openfoodfacts.org/*' => Http::response([
            'status' => 'success', 'product' => ['code' => '3017620422003', 'product_name' => 'Pâte à tartiner', 'brands' => 'Marque test', 'nutriments' => ['sugars_100g' => 56.3]],
        ])]);
        $owner = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $transformer = User::factory()->create(['role' => 'transformateur', 'status' => 'approved']);
        $product = Product::create(['user_id' => $owner->id, 'name' => 'Produit local', 'reference' => 'OFF-LOCAL-1', 'category' => 'Alimentaire', 'unit' => 'kg', 'status' => 'published']);

        $this->actingAs($transformer)->get(route('workspace.open-food-facts.search', ['barcode' => '3017620422003']))
            ->assertOk()->assertSee('Pâte à tartiner')->assertSee('Marque test')->assertSee('Valeurs nutritionnelles');

        $this->post(route('workspace.open-food-facts.import'), [
            'local_product_id' => $product->id, 'barcode' => '3017620422003', 'fields' => ['name', 'brands'],
        ])->assertRedirect(route('workspace.open-food-facts.index'));
        $product->refresh();
        $this->assertSame(['name' => 'Pâte à tartiner', 'brands' => 'Marque test'], $product->open_food_facts_data);
        $this->assertSame('3017620422003', $product->open_food_facts_code);
        $this->assertSame('open_food_facts', $product->open_food_facts_source);
        $this->assertSame('Produit local', $product->name);
    }

    public function test_manual_import_preserves_unselected_data_and_requires_overwrite_confirmation(): void
    {
        $owner = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $transformer = User::factory()->create(['role' => 'transformateur', 'status' => 'approved']);
        $product = Product::create(['user_id' => $owner->id, 'name' => 'Produit local', 'reference' => 'OFF-LOCAL-2', 'category' => 'Alimentaire', 'unit' => 'kg', 'status' => 'published', 'open_food_facts_data' => ['name' => 'Nom existant', 'brands' => 'Marque existante']]);
        $this->actingAs($transformer)->post(route('workspace.open-food-facts.import'), [
            'local_product_id' => $product->id, 'manual_mode' => 1, 'manual' => ['name' => 'Nouveau nom', 'categories' => 'Huiles'], 'fields' => ['name', 'categories'],
        ])->assertSessionHasErrors('overwrite');

        $this->post(route('workspace.open-food-facts.import'), [
            'local_product_id' => $product->id, 'manual_mode' => 1, 'manual' => ['name' => 'Nouveau nom', 'categories' => 'Huiles'], 'fields' => ['name', 'categories'], 'overwrite' => 1,
        ])->assertRedirect();
        $this->assertSame(['name' => 'Nouveau nom', 'brands' => 'Marque existante', 'categories' => 'Huiles'], $product->fresh()->open_food_facts_data);
    }

    public function test_text_search_uses_documented_search_service_with_limited_fields(): void
    {
        Cache::flush();
        Http::fake(['search.openfoodfacts.org/*' => Http::response([
            'hits' => [['code' => '6111009000012', 'product_name' => 'Huile d’olive', 'brands' => 'Marque locale']],
        ])]);
        $transformer = User::factory()->create(['role' => 'transformateur', 'status' => 'approved']);
        $this->actingAs($transformer)->get(route('workspace.open-food-facts.search', ['query' => 'huile olive']))
            ->assertOk()->assertSee('Huile d’olive')->assertSee('6111009000012');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'search.openfoodfacts.org/search')
            && $request->method() === 'POST'
            && $request->data()['q'] === 'huile olive'
            && count($request->data()['fields']) < 20
            && str_contains($request->header('User-Agent')[0] ?? '', 'NutriTrace/1.0'));
    }

    public function test_rate_limit_from_open_food_facts_provides_a_manual_entry_fallback(): void
    {
        Cache::flush();
        Http::fake(['world.openfoodfacts.org/*' => Http::response([], 429)]);
        $transformer = User::factory()->create(['role' => 'transformateur', 'status' => 'approved']);
        $this->actingAs($transformer)->get(route('workspace.open-food-facts.search', ['barcode' => '6111009000012']))
            ->assertOk()->assertSee('limite temporairement les recherches')->assertSee('Saisie manuelle')
            ->assertSee('id="manual-entry-dialog"', false)->assertSee('data-open-manual-dialog', false);
    }

    public function test_name_search_is_used_when_the_submitted_barcode_has_no_match(): void
    {
        Cache::flush();
        Http::fake([
            'world.openfoodfacts.org/*' => Http::response(['status' => 'failure', 'product' => null]),
            'search.openfoodfacts.org/*' => Http::response(['hits' => [
                ['code' => '6191660000300', 'product_name' => 'Denya', 'generic_name' => ['fr' => 'Produit laitier', 'en' => 'Dairy product'], 'brands' => ['Denya', 'Denya Foods']],
            ]]),
        ]);
        $transformer = User::factory()->create(['role' => 'transformateur', 'status' => 'approved']);

        $this->actingAs($transformer)->get(route('workspace.open-food-facts.search', ['query' => 'denya', 'barcode' => '61916600030']))
            ->assertOk()->assertSee('Voici les résultats correspondant au nom saisi')->assertSee('Denya')->assertSee('6191660000300');
    }

    public function test_other_roles_cannot_access_the_catalogue_or_import_data(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer)->get(route('workspace.open-food-facts.index'))->assertForbidden();
    }

}
