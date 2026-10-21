<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TraceabilityCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_producer_can_analyze_bom_semicolon_csv_and_prefill_without_persisting(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer);
        $csv = "\xEF\xBB\xBFNom;Référence;Localisation;Pays;Colonne inconnue\r\nOliviers;FARM-CSV-1;Sfax;Tunisie;Ignorer\r\n";

        $this->post(route('workspace.resources.import.analyze', 'farms'), [
            'file' => UploadedFile::fake()->createWithContent('exploitations.csv', $csv),
        ])->assertRedirect(route('workspace.resources.import', ['resource' => 'farms', 'popup' => 1]));

        $this->assertDatabaseMissing('farms', ['reference' => 'FARM-CSV-1']);
        $this->get(route('workspace.resources.import', ['resource' => 'farms', 'popup' => 1]))
            ->assertOk()->assertSee('Oliviers')->assertSee('Colonne inconnue')->assertSee('Télécharger le modèle');
        $this->post(route('workspace.resources.import-row', 'farms'), ['row' => 0])
            ->assertRedirect(route('workspace.resources.index', ['resource' => 'farms', 'modal' => 'create']))
            ->assertSessionHas('_old_input.name', 'Oliviers');
    }

    public function test_csv_incomplete_values_are_previewed_for_manual_completion_and_invalid_values_are_flagged(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $farm = Farm::create(['user_id' => $producer->id, 'name' => 'Ferme CSV', 'reference' => 'FARM-OWNER-CSV', 'location' => 'Sfax', 'country' => 'Tunisie']);
        $this->actingAs($producer);

        $validIncomplete = "name,reference,category,unit,status,quantity,farm_id\nOlives,PROD-CSV-1,,kg,draft,10,{$farm->id}\n";
        $this->post(route('workspace.resources.import.analyze', 'products'), ['file' => UploadedFile::fake()->createWithContent('products.csv', $validIncomplete)])
            ->assertRedirect();
        $this->get(route('workspace.resources.import', ['resource' => 'products', 'popup' => 1]))
            ->assertOk()->assertSee('Champ obligatoire à compléter');
        $this->assertDatabaseMissing('products', ['reference' => 'PROD-CSV-1']);

        $invalid = "name,reference,category,unit,status,quantity\nOlives,PROD-CSV-2,Fruits,kg,draft,-4\n";
        $this->post(route('workspace.resources.import.analyze', 'products'), ['file' => UploadedFile::fake()->createWithContent('invalid.csv', $invalid)])
            ->assertRedirect();
        $this->get(route('workspace.resources.import', ['resource' => 'products', 'popup' => 1]))
            ->assertOk()->assertSee('ne peut pas être négative');
    }

    public function test_wrong_headers_empty_file_and_unauthorized_import_are_rejected(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer);
        $this->from(route('workspace.resources.index', 'farms'))->post(route('workspace.resources.import.analyze', 'farms'), [
            'file' => UploadedFile::fake()->createWithContent('wrong.csv', "foo;bar\nx;y\n"),
        ])->assertSessionHasErrors('file');

        $this->post(route('workspace.resources.import.analyze', 'farms'), [
            'file' => UploadedFile::fake()->createWithContent('empty.csv', ''),
        ])->assertSessionHasErrors('file');

        $distributor = User::factory()->create(['role' => 'distributeur', 'status' => 'approved']);
        $this->actingAs($distributor)->get(route('workspace.resources.import', 'farms'))->assertForbidden();
    }

    public function test_export_format_headers_are_recognized_by_the_import_analyzer(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $product = Product::create(['user_id' => $producer->id, 'name' => 'Produit CSV', 'reference' => 'PROD-EXPORT-CSV', 'category' => 'Fruits', 'unit' => 'kg', 'status' => 'draft']);
        $this->actingAs($producer)->get(route('workspace.resources.export', 'products'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'reference' => 'PROD-EXPORT-CSV']);
    }
}
