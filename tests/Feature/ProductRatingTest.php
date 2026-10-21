<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRatingTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);

        return Product::create([
            'user_id' => $producer->id,
            'name' => 'Olives biologiques',
            'reference' => 'PROD-RATING-1',
            'category' => 'Fruits',
            'origin' => 'Sfax',
            'description' => 'Produit de test',
            'quantity' => 25,
            'unit' => 'kg',
            'status' => 'published',
        ]);
    }

    public function test_consumer_can_submit_one_product_rating_but_cannot_edit_it(): void
    {
        $product = $this->product();
        $consumer = User::factory()->create(['role' => 'consommateur', 'status' => 'approved']);
        $url = route('consumer.product-ratings.store', $product);

        $this->actingAs($consumer)->post($url, ['rating' => 4, 'comment' => 'Très bon produit.'])
            ->assertRedirect(route('public.products.show', $product));
        $this->assertDatabaseHas('product_ratings', [
            'product_id' => $product->id, 'consumer_id' => $consumer->id, 'rating' => 4,
            'comment' => 'Très bon produit.', 'status' => 'published',
        ]);

        $this->post($url, ['rating' => 5, 'comment' => 'Encore meilleur.'])->assertRedirect()
            ->assertSessionHas('status', 'Vous avez déjà envoyé un avis pour ce produit. Il ne peut pas être modifié.');
        $this->assertSame(1, ProductRating::where('product_id', $product->id)->where('consumer_id', $consumer->id)->count());
        $this->assertDatabaseHas('product_ratings', ['product_id' => $product->id, 'consumer_id' => $consumer->id, 'rating' => 4, 'comment' => 'Très bon produit.']);
    }

    public function test_rating_input_is_validated_and_requires_approved_consumer_account(): void
    {
        $product = $this->product();
        $consumer = User::factory()->create(['role' => 'consommateur', 'status' => 'approved']);

        $this->actingAs($consumer)->post(route('consumer.product-ratings.store', $product), ['rating' => 6])
            ->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('product_ratings', 0);

        $producer = User::factory()->create(['role' => 'producteur', 'status' => 'approved']);
        $this->actingAs($producer)->post(route('consumer.product-ratings.store', $product), ['rating' => 5])
            ->assertForbidden();
        auth()->logout();
        $this->post(route('consumer.product-ratings.store', $product), ['rating' => 5])->assertRedirect(route('public.products.show', $product));
        $this->assertDatabaseHas('product_ratings', ['product_id' => $product->id, 'consumer_id' => null, 'rating' => 5]);
    }

    public function test_guest_can_submit_review_without_an_account(): void
    {
        $product = $this->product();
        $url = route('consumer.product-ratings.store', $product);

        $this->withSession(['_token' => 'guest-session-test'])->post($url, [
            '_token' => 'guest-session-test', 'rating' => 4, 'name' => 'Visiteur', 'comment' => 'Bon produit.', 'website' => '',
        ])->assertRedirect(route('public.products.show', $product));
        $this->assertDatabaseHas('product_ratings', [
            'product_id' => $product->id, 'consumer_id' => null, 'consumer_name' => 'Visiteur', 'rating' => 4, 'comment' => 'Bon produit.',
        ]);
        $this->get(route('public.products.show', $product))->assertOk()->assertSee('Visiteur')->assertSee('Bon produit.');
    }

    public function test_public_product_page_shows_only_published_reviews_and_real_aggregate(): void
    {
        $product = $this->product();
        $first = User::factory()->create(['role' => 'consommateur', 'status' => 'approved']);
        $second = User::factory()->create(['role' => 'consommateur', 'status' => 'approved']);
        $third = User::factory()->create(['role' => 'consommateur', 'status' => 'approved']);
        ProductRating::create(['product_id' => $product->id, 'consumer_id' => $first->id, 'rating' => 4, 'comment' => 'Avis visible', 'status' => 'published']);
        ProductRating::create(['product_id' => $product->id, 'consumer_id' => $second->id, 'rating' => 2, 'comment' => 'Autre avis', 'status' => 'published']);
        ProductRating::create(['product_id' => $product->id, 'consumer_id' => $third->id, 'rating' => 1, 'comment' => 'Avis caché', 'status' => 'hidden']);

        $this->get(route('public.products.show', $product))->assertOk()
            ->assertSee('3,0/5')->assertSee('Avis visible')->assertDontSee('Avis caché');

        ProductRating::query()->where('status', 'published')->delete();
        $this->get(route('public.products.show', $product))->assertOk()->assertSee('Aucune évaluation pour le moment.');
    }

    public function test_anonymous_reviews_are_listed_on_admin_dashboard_and_management_screen(): void
    {
        $product = $this->product();
        ProductRating::create([
            'product_id' => $product->id, 'consumer_name' => 'Client invite', 'guest_token' => hash('sha256', 'guest'),
            'rating' => 5, 'comment' => 'Bien reçu', 'status' => 'published',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Avis consommateurs')->assertSee('Client invite')->assertSee('Bien reçu');
        $this->get(route('admin.product-ratings.index', ['q' => 'Client invite']))->assertOk()
            ->assertSee('Client invite')->assertSee('Olives biologiques');
    }

    public function test_admin_can_filter_review_detail_and_moderate_publication(): void
    {
        $product = $this->product();
        $consumer = User::factory()->create(['role' => 'consommateur', 'status' => 'approved']);
        $rating = ProductRating::create(['product_id' => $product->id, 'consumer_id' => $consumer->id, 'rating' => 3, 'comment' => 'À vérifier', 'status' => 'published']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);

        $this->actingAs($admin)->get(route('admin.product-ratings.index', ['q' => 'Olives', 'rating' => 3]))
            ->assertOk()->assertSee('Olives biologiques')->assertSee('À vérifier');
        $this->get(route('admin.product-ratings.show', $rating))->assertOk()->assertSee('À vérifier');
        $this->patch(route('admin.product-ratings.publication', $rating), ['status' => 'hidden'])
            ->assertRedirect(route('admin.product-ratings.show', $rating));
        $this->assertDatabaseHas('product_ratings', ['id' => $rating->id, 'status' => 'hidden']);
        $this->get(route('public.products.show', $product))->assertOk()->assertDontSee('À vérifier');

        $this->patch(route('admin.product-ratings.publication', $rating), ['status' => 'published'])->assertRedirect();
        $this->get(route('public.products.show', $product))->assertOk()->assertSee('À vérifier');
    }
}
