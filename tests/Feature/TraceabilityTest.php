<?php

namespace Tests\Feature;

use App\Models\Certification;
use App\Models\Distribution;
use App\Models\EnvironmentalIndicator;
use App\Models\Farm;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Store;
use App\Models\TraceabilityAlert;
use App\Models\Transformation;
use App\Models\User;
use App\Services\TraceabilityAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class TraceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_producer_can_create_owned_farm_product_and_traceable_lot(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer)->post(route('workspace.resources.store', 'farms'), [
            'name' => 'Ferme du Nord', 'reference' => 'FARM-T-1', 'location' => 'Bizerte', 'country' => 'Tunisie',
        ])->assertRedirect();

        $farm = Farm::where('reference', 'FARM-T-1')->firstOrFail();
        $this->assertSame($producer->id, $farm->user_id);

        $this->post(route('workspace.resources.store', 'products'), [
            'name' => 'Tomates', 'reference' => 'PROD-T-1', 'category' => 'Légumes', 'farm_id' => $farm->id,
            'origin' => 'Bizerte', 'quantity' => 100, 'unit' => 'kg', 'status' => 'published',
        ])->assertRedirect();

        $product = Product::where('reference', 'PROD-T-1')->firstOrFail();
        $this->post(route('workspace.resources.store', 'lots'), [
            'product_id' => $product->id, 'code' => 'LOT-T-1', 'quantity' => 90, 'unit' => 'kg', 'status' => 'active',
            'produced_at' => today()->toDateString(),
        ])->assertRedirect();

        $lot = Lot::where('code', 'LOT-T-1')->firstOrFail();
        $this->assertNotEmpty($lot->public_id);
        $this->get(route('public.trace', $lot->public_id))->assertOk()->assertSee('Tomates')->assertSee('LOT-T-1');
        $this->get(route('public.catalogue'))->assertOk()->assertSee('Tomates');
    }

    public function test_users_cannot_view_or_manage_another_actors_private_records(): void
    {
        $owner = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $otherProducer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $owner->id, 'name' => 'Ferme privée', 'reference' => 'FARM-PRIVATE', 'location' => 'Sfax', 'country' => 'Tunisie']);

        $this->actingAs($otherProducer)->get(route('workspace.resources.show', ['farms', $farm]))->assertNotFound();
        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $this->actingAs($distributor)->get(route('workspace.resources.index', 'farms'))->assertForbidden();
    }

    public function test_admin_can_render_business_dashboard_and_module_screens(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Produits');

        foreach (['farms', 'products', 'lots', 'transformations', 'stores', 'distributions', 'certifications', 'indicators'] as $resource) {
            $this->get(route('workspace.resources.index', $resource))->assertOk();
            $this->get(route('workspace.resources.create', $resource))->assertOk();
        }
    }

    public function test_producer_screens_persist_relations_and_document_in_database(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer);

        $this->get(route('workspace.resources.index', 'products'))->assertOk()->assertSee('Traçabilité complète');
        $this->get(route('workspace.resources.index', ['farms', 'modal' => 'create']))->assertOk()->assertSee('Justificatif');

        $this->post(route('workspace.resources.store', 'products'), [
            'name' => 'Tomates de test', 'reference' => 'PRD-FORM-1', 'category' => 'Légumes',
            'origin' => 'Nabeul', 'quantity' => 12, 'unit' => 'kg', 'status' => 'draft',
        ])->assertRedirect();
        $product = Product::where('reference', 'PRD-FORM-1')->firstOrFail();

        $this->post(route('workspace.resources.store', 'farms'), [
            'name' => 'Ferme de test', 'reference' => 'FARM-FORM-1', 'location' => 'Nabeul',
            'country' => 'Tunisie', 'farming_method' => 'Biologique', 'started_at' => '2025-05-01',
            'status' => 'active', 'associated_products' => [$product->id],
            'document' => UploadedFile::fake()->createWithContent('preuve.pdf', '%PDF-1.4 nutritrace-test'),
        ])->assertRedirect();

        $farm = Farm::where('reference', 'FARM-FORM-1')->firstOrFail();
        $this->assertSame($farm->id, $product->fresh()->farm_id);
        $this->assertSame('2025-05-01', $farm->started_at->toDateString());
        $this->assertNotEmpty($farm->document_contents);
        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'document_name' => 'preuve.pdf']);
        $this->get(route('workspace.farms.document', $farm))->assertOk()->assertSee('%PDF-1.4 nutritrace-test');
        $this->get(route('workspace.resources.export', 'farms'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_distributor_cannot_ship_more_than_the_active_published_lot_quantity(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme', 'reference' => 'FARM-D-1', 'location' => 'Sousse', 'country' => 'Tunisie']);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => 'Huile', 'reference' => 'PROD-D-1', 'category' => 'Épicerie', 'unit' => 'L', 'status' => 'published']);
        $lot = Lot::create(['product_id' => $product->id, 'created_by' => $producer->id, 'code' => 'LOT-D-1', 'public_id' => (string) Str::uuid(), 'quantity' => 10, 'unit' => 'L', 'status' => 'active']);
        $store = Store::create(['user_id' => $distributor->id, 'name' => 'Marché', 'reference' => 'STORE-D-1', 'address' => 'Centre-ville', 'country' => 'Tunisie', 'status' => 'active']);

        $this->actingAs($distributor)->post(route('workspace.resources.store', 'distributions'), [
            'reference' => 'DIST-D-1', 'lot_id' => $lot->id, 'store_id' => $store->id, 'quantity_shipped' => 11,
            'unit' => 'L', 'status' => 'planned',
        ])->assertSessionHasErrors('quantity_shipped');

        $this->assertDatabaseMissing('distributions', ['reference' => 'DIST-D-1']);
    }

    public function test_producer_certifications_remain_pending_until_admin_validation(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme certifiée', 'reference' => 'FARM-C-1', 'location' => 'Nabeul', 'country' => 'Tunisie']);

        $this->actingAs($producer)->post(route('workspace.resources.store', 'certifications'), [
            'title' => 'Bio', 'scheme' => 'Bio', 'certificate_number' => 'CERT-C-1', 'issuer' => 'Organisme',
            'farm_id' => $farm->id, 'status' => 'valid',
        ])->assertRedirect();

        $this->assertSame('pending', Certification::where('certificate_number', 'CERT-C-1')->value('status'));
    }

    public function test_deterministic_analysis_records_explainable_alerts_without_duplicates(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme analyse', 'reference' => 'FARM-A-1', 'location' => 'Tunis', 'country' => 'Tunisie']);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => 'Produit analyse', 'reference' => 'PROD-A-1', 'category' => 'Frais', 'unit' => 'kg', 'status' => 'published']);
        $lot = Lot::create(['product_id' => $product->id, 'created_by' => $producer->id, 'code' => 'LOT-A-1', 'public_id' => (string) Str::uuid(), 'quantity' => 20, 'unit' => 'kg', 'status' => 'active', 'expires_at' => today()->subDay()]);
        Certification::create(['created_by' => $producer->id, 'farm_id' => $farm->id, 'title' => 'Bio', 'scheme' => 'Bio', 'certificate_number' => 'CERT-A-1', 'issuer' => 'Organisme', 'expires_at' => today()->subDay(), 'status' => 'valid']);
        Store::create(['user_id' => $distributor->id, 'name' => 'Point de vente analyse', 'reference' => 'STORE-A-1', 'address' => 'Tunis', 'country' => 'Tunisie']);
        $store = Store::where('reference', 'STORE-A-1')->firstOrFail();
        Distribution::create(['user_id' => $distributor->id, 'lot_id' => $lot->id, 'store_id' => $store->id, 'reference' => 'DIST-A-1', 'quantity_shipped' => 2, 'unit' => 'kg', 'expected_at' => now()->subDay(), 'status' => 'in_transit']);
        EnvironmentalIndicator::create(['recorded_by' => $producer->id, 'product_id' => $product->id, 'stage' => 'production', 'metric_code' => 'co2e', 'name' => 'Emissions', 'value' => 2, 'unit' => 'kgCO2e', 'method' => null]);

        $service = app(TraceabilityAnalysisService::class);
        $service->analyze();
        $count = TraceabilityAlert::count();
        $service->analyze();

        $this->assertSame($count, TraceabilityAlert::count());
        $this->assertDatabaseHas('traceability_alerts', ['code' => 'lot_expired', 'subject_id' => $lot->id]);
        $this->assertDatabaseHas('traceability_alerts', ['code' => 'certification_expired']);
        $this->assertDatabaseHas('traceability_alerts', ['code' => 'distribution_late']);
        $this->assertDatabaseHas('traceability_alerts', ['code' => 'indicator_missing_method']);
    }

    public function test_distributor_map_shows_only_owned_stores_and_distributions(): void
    {
        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $otherDistributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        Store::create(['user_id' => $distributor->id, 'name' => 'Mon point de vente', 'reference' => 'MAP-OWN', 'address' => 'Rue principale', 'city' => 'Tunis', 'country' => 'Tunisie', 'latitude' => 36.8065, 'longitude' => 10.1815]);
        Store::create(['user_id' => $otherDistributor->id, 'name' => 'Point privé', 'reference' => 'MAP-OTHER', 'address' => 'Adresse privée', 'city' => 'Sfax', 'country' => 'Tunisie', 'latitude' => 34.7406, 'longitude' => 10.7603]);

        $this->actingAs($distributor)
            ->get(route('workspace.map-distances'))
            ->assertOk()
            ->assertSee('Mon point de vente')
            ->assertDontSee('Point privé')
            ->assertSee('Ma position');

        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer)->get(route('workspace.map-distances'))->assertForbidden();
    }

    public function test_distributor_can_save_valid_optional_coordinates_for_stores_and_distribution_origin(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme carte', 'reference' => 'MAP-FARM', 'location' => 'Nabeul', 'country' => 'Tunisie']);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => 'Tomates carte', 'reference' => 'MAP-PROD', 'category' => 'Légumes', 'status' => 'published']);
        $lot = Lot::create(['product_id' => $product->id, 'created_by' => $producer->id, 'code' => 'MAP-LOT', 'public_id' => (string) Str::uuid(), 'quantity' => 12, 'unit' => 'kg', 'status' => 'active']);
        $this->actingAs($distributor);

        $this->post(route('workspace.resources.store', 'stores'), [
            'name' => 'Point géolocalisé', 'reference' => 'MAP-STORE', 'address' => 'Centre-ville', 'country' => 'Tunisie',
            'latitude' => '36.8065000', 'longitude' => '10.1815000', 'status' => 'active',
        ])->assertRedirect();
        $store = Store::where('reference', 'MAP-STORE')->firstOrFail();

        $this->post(route('workspace.resources.store', 'distributions'), [
            'reference' => 'MAP-DIST', 'lot_id' => $lot->id, 'store_id' => $store->id, 'quantity_shipped' => 3,
            'unit' => 'kg', 'status' => 'planned', 'origin_name' => 'Entrepôt Tunis',
            'origin_latitude' => '36.8000000', 'origin_longitude' => '10.1700000',
        ])->assertRedirect();

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'latitude' => 36.8065, 'longitude' => 10.1815]);
        $this->assertDatabaseHas('distributions', ['reference' => 'MAP-DIST', 'origin_name' => 'Entrepôt Tunis', 'origin_latitude' => 36.8, 'origin_longitude' => 10.17]);
    }

    public function test_store_coordinates_must_be_a_valid_pair(): void
    {
        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);

        $this->actingAs($distributor)->post(route('workspace.resources.store', 'stores'), [
            'name' => 'Coordonnées invalides', 'reference' => 'MAP-INVALID', 'address' => 'Tunis', 'country' => 'Tunisie',
            'latitude' => '91', 'status' => 'active',
        ])->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertDatabaseMissing('stores', ['reference' => 'MAP-INVALID']);
    }

    public function test_lot_date_order_is_validated_while_historical_dates_remain_allowed(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme historique', 'reference' => 'DATE-FARM', 'location' => 'Nabeul', 'country' => 'Tunisie']);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => 'Produit historique', 'reference' => 'DATE-PRODUCT', 'category' => 'Fruits', 'unit' => 'kg', 'status' => 'published']);
        $this->actingAs($producer);

        $payload = [
            'product_id' => $product->id, 'code' => 'DATE-LOT', 'quantity' => 10, 'unit' => 'kg', 'status' => 'active',
            'produced_at' => '2020-04-10', 'expires_at' => '2020-04-09',
        ];
        $this->post(route('workspace.resources.store', 'lots'), $payload)
            ->assertSessionHasErrors('expires_at');
        $this->assertDatabaseMissing('lots', ['code' => 'DATE-LOT']);

        $payload['expires_at'] = '2020-04-20';
        $this->post(route('workspace.resources.store', 'lots'), $payload)->assertRedirect();
        $this->assertDatabaseHas('lots', ['code' => 'DATE-LOT', 'produced_at' => '2020-04-10 00:00:00', 'expires_at' => '2020-04-20 00:00:00']);
    }

    public function test_required_field_errors_are_translated_and_returned_for_the_specific_field(): void
    {
        $this->assertSame('Veuillez saisir une adresse e-mail valide.', trans('validation.email'));
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer)->from(route('workspace.resources.create', 'products'))
            ->post(route('workspace.resources.store', 'products'), ['name' => '   '])
            ->assertSessionHasErrors(['name' => 'Le champ nom est obligatoire.']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_distribution_expected_and_delivery_dates_cannot_precede_shipment(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme transport', 'reference' => 'SHIP-FARM', 'location' => 'Sfax', 'country' => 'Tunisie']);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => 'Produit transport', 'reference' => 'SHIP-PRODUCT', 'category' => 'Frais', 'unit' => 'kg', 'status' => 'published']);
        $lot = Lot::create(['product_id' => $product->id, 'created_by' => $producer->id, 'code' => 'SHIP-LOT', 'public_id' => (string) Str::uuid(), 'quantity' => 50, 'unit' => 'kg', 'status' => 'active']);
        $store = Store::create(['user_id' => $distributor->id, 'name' => 'Point transport', 'reference' => 'SHIP-STORE', 'address' => 'Tunis', 'country' => 'Tunisie', 'status' => 'active']);

        $this->actingAs($distributor)->post(route('workspace.resources.store', 'distributions'), [
            'reference' => 'SHIP-DIST', 'lot_id' => $lot->id, 'store_id' => $store->id,
            'quantity_shipped' => 10, 'unit' => 'kg', 'status' => 'planned',
            'shipped_at' => '2026-10-21T12:00', 'expected_at' => '2026-10-20T12:00', 'delivered_at' => '2026-10-19T12:00',
        ])->assertSessionHasErrors(['expected_at', 'delivered_at']);
    }

    public function test_producer_qr_page_lists_only_owned_products_and_filters_them(): void
    {
        config()->set('app.public_url', 'https://nutritrace.tn');
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $otherProducer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $own = Product::create(['user_id' => $producer->id, 'name' => 'Mon produit QR', 'reference' => 'QR-OWN', 'category' => 'Fruits', 'status' => 'published']);
        $ownSecond = Product::create(['user_id' => $producer->id, 'name' => 'Mon second QR', 'reference' => 'QR-OWN-2', 'category' => 'Fruits', 'status' => 'published']);
        Product::create(['user_id' => $otherProducer->id, 'name' => 'Produit privé QR', 'reference' => 'QR-OTHER', 'category' => 'Fruits', 'status' => 'published']);

        $this->actingAs($producer)->get(route('workspace.qr-codes.index'))
            ->assertOk()->assertSee('Mon produit QR')->assertDontSee('Produit privé QR')
            ->assertSee('https://nutritrace.tn'.route('public.products.qr', $own->public_id, false))
            ->assertSee('https://nutritrace.tn'.route('public.products.qr', $ownSecond->public_id, false));
        $this->assertNotSame($own->public_id, $ownSecond->public_id);

        $this->get(route('workspace.qr-codes.index', ['q' => 'QR-OWN', 'status' => 'published']))
            ->assertOk()->assertSee('Mon produit QR')->assertDontSee('Produit privé QR');

        $consumer = User::factory()->create(['role' => 'consommateur', 'status' => 'approved']);
        $this->actingAs($consumer)->get(route('workspace.qr-codes.index'))->assertForbidden();
    }

    public function test_qr_page_never_offers_localhost_as_a_consumer_url(): void
    {
        config()->set('app.public_url', 'http://localhost:8001');
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        Product::create(['user_id' => $producer->id, 'name' => 'Produit local', 'reference' => 'QR-LOCAL', 'category' => 'Fruits', 'status' => 'published']);

        $this->actingAs($producer)->get(route('workspace.qr-codes.index'))
            ->assertOk()->assertSee('QR non disponible')->assertDontSee('localhost:8001/p/', false);
    }

    public function test_environmental_indicator_must_be_validated_by_an_admin_before_public_display(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $product = Product::create(['user_id' => $producer->id, 'name' => 'Produit mesure', 'reference' => 'QR-INDICATOR', 'category' => 'Fruits', 'status' => 'published']);
        $payload = ['product_id' => $product->id, 'stage' => 'production', 'metric_code' => 'co2e', 'name' => 'Mesure test', 'value' => 1.2, 'unit' => 'kgCO2e'];

        $this->actingAs($producer)->post(route('workspace.resources.store', 'indicators'), $payload + ['status' => 'valid'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('environmental_indicators', ['name' => 'Mesure test']);

        $this->post(route('workspace.resources.store', 'indicators'), $payload)->assertRedirect();
        $indicator = EnvironmentalIndicator::where('name', 'Mesure test')->firstOrFail();
        $this->assertSame('pending', $indicator->status);
        $this->get(route('public.products.qr', $product->public_id))->assertOk()->assertDontSee('Mesure test');

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $this->actingAs($admin)->put(route('workspace.resources.update', ['indicators', $indicator->id]), $payload + ['status' => 'valid'])->assertRedirect();
        $this->assertSame('valid', $indicator->fresh()->status);
        $this->assertSame($admin->id, $indicator->fresh()->validated_by);
        $this->get(route('public.products.qr', $product->public_id))->assertOk()->assertSee('Mesure test');
    }

    public function test_public_product_qr_url_is_stable_and_shows_only_publishable_public_data(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme QR publique', 'reference' => 'QR-FARM', 'location' => 'Nabeul', 'country' => 'Tunisie']);
        $product = Product::create(['user_id' => $producer->id, 'farm_id' => $farm->id, 'name' => 'Tomates QR publiques', 'reference' => 'QR-PROD', 'category' => 'Légumes', 'description' => 'Description publique du produit', 'status' => 'published']);
        $otherPublicProduct = Product::create(['user_id' => $producer->id, 'name' => 'Autre produit QR', 'reference' => 'QR-PROD-OTHER', 'category' => 'Fruits', 'status' => 'published']);
        $lot = Lot::create(['product_id' => $product->id, 'created_by' => $producer->id, 'code' => 'QR-LOT-PUBLIC', 'public_id' => (string) Str::uuid(), 'quantity' => 10, 'unit' => 'kg', 'status' => 'active', 'produced_at' => today()->subDays(2)]);
        $transformer = User::factory()->create(['role' => 'transformateur', 'status' => 'approved']);
        $transformation = Transformation::create(['user_id' => $transformer->id, 'reference' => 'QR-TRANSFORMATION', 'name' => 'Tri enregistré', 'performed_at' => today()->subDay(), 'facility' => 'Centre de tri', 'status' => 'recorded']);
        $transformation->lots()->attach($lot->id, ['direction' => 'output', 'quantity' => 9, 'unit' => 'kg']);
        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $store = Store::create(['user_id' => $distributor->id, 'name' => 'Marché public', 'reference' => 'QR-STORE', 'address' => 'Rue privée non publiée', 'city' => 'Tunis', 'country' => 'Tunisie', 'contact_name' => 'CONTACT PRIVÉ', 'status' => 'active']);
        Distribution::create(['user_id' => $distributor->id, 'lot_id' => $lot->id, 'store_id' => $store->id, 'reference' => 'QR-DISTRIBUTION', 'quantity_shipped' => 8, 'quantity_delivered' => 8, 'unit' => 'kg', 'shipped_at' => now(), 'delivered_at' => now(), 'status' => 'delivered']);
        EnvironmentalIndicator::create(['recorded_by' => $producer->id, 'product_id' => $product->id, 'stage' => 'production', 'metric_code' => 'co2e', 'name' => 'Empreinte CO2', 'value' => 0.42, 'unit' => 'kgCO2e', 'method' => 'Mesure documentée', 'source_notes' => 'NE PAS EXPOSER note interne privée', 'status' => 'valid', 'validated_by' => $producer->id, 'validated_at' => now()]);
        EnvironmentalIndicator::create(['recorded_by' => $producer->id, 'product_id' => $product->id, 'stage' => 'production', 'metric_code' => 'water', 'name' => 'Mesure en attente', 'value' => 2, 'unit' => 'L', 'status' => 'pending']);
        $draft = Product::create(['user_id' => $producer->id, 'name' => 'Produit QR brouillon', 'reference' => 'QR-DRAFT', 'category' => 'Fruits', 'status' => 'draft']);
        $deleted = Product::create(['user_id' => $producer->id, 'name' => 'Produit QR supprimé', 'reference' => 'QR-DELETED', 'category' => 'Fruits', 'status' => 'published']);
        $deleted->delete();

        $this->assertNotEmpty($product->public_id);
        $this->assertSame($product->public_id, $product->fresh()->public_id);
        $url = route('public.products.qr', $product->public_id);
        $this->get($url)->assertOk()->assertSee('Tomates QR publiques')->assertSee('Description publique du produit')->assertSee($producer->name)
            ->assertSee('QR-LOT-PUBLIC')->assertSee('Tri enregistré')->assertSee('Marché public')
            ->assertSee('Empreinte CO2')->assertDontSee('Mesure en attente')->assertDontSee('NE PAS EXPOSER note interne privée')->assertDontSee('CONTACT PRIVÉ')->assertDontSee('Rue privée non publiée');
        $this->assertNotSame($product->public_id, $otherPublicProduct->public_id);
        $this->get(route('public.products.qr', $otherPublicProduct->public_id))->assertOk()->assertSee('Autre produit QR')->assertDontSee('Tomates QR publiques');
        $this->get(route('public.products.qr', $draft->public_id))->assertNotFound();
        $this->get(route('public.products.qr', $deleted->public_id))->assertNotFound();

        $product->owner()->update(['status' => 'suspended']);
        $this->get($url)->assertNotFound();
    }
}
