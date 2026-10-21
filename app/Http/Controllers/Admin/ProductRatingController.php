<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductRatingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'status' => ['nullable', Rule::in(ProductRating::STATUSES)],
        ]);

        $publishedRatings = ProductRating::query()->published();
        $summary = (clone $publishedRatings)->selectRaw('COUNT(*) as total, AVG(rating) as average')->first();
        $distribution = (clone $publishedRatings)->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');

        $rankedProducts = static fn () => ProductRating::query()->published()
            ->selectRaw('product_id, AVG(rating) as average_rating, COUNT(*) as ratings_count')
            ->groupBy('product_id')->havingRaw('COUNT(*) >= ?', [3])->with('product:id,name');

        $topProducts = $rankedProducts()->orderByDesc('average_rating')->orderByDesc('ratings_count')->limit(5)->get();
        $lowestProducts = $rankedProducts()->orderBy('average_rating')->orderByDesc('ratings_count')->limit(5)->get();

        $ratings = ProductRating::query()->with(['consumer:id,name', 'product:id,name'])
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $term = '%'.trim($term).'%';
                $query->where(fn ($nested) => $nested
                    ->where('consumer_name', 'like', $term)
                    ->orWhere('comment', 'like', $term)
                    ->orWhereHas('consumer', fn ($consumer) => $consumer->where('name', 'like', $term))
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', $term)));
            })
            ->when($filters['rating'] ?? null, fn ($query, $rating) => $query->where('rating', $rating))
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->where('product_id', $productId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(5)->withQueryString();

        return view('admin.product-ratings.index', [
            'ratings' => $ratings,
            'filters' => $filters,
            'products' => Product::query()->whereHas('ratings')->orderBy('name')->get(['id', 'name']),
            'totalRatings' => (int) ($summary->total ?? 0),
            'averageRating' => $summary->average,
            'distribution' => $distribution,
            'topProducts' => $topProducts,
            'lowestProducts' => $lowestProducts,
        ]);
    }

    public function show(Request $request, ProductRating $productRating): View
    {
        $rating = $productRating->load(['consumer:id,name,email', 'product:id,name,reference']);

        return view($request->boolean('popup')
            ? 'admin.product-ratings.preview'
            : 'admin.product-ratings.show', compact('rating'));
    }

    public function togglePublication(Request $request, ProductRating $productRating): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(ProductRating::STATUSES)],
        ]);

        $productRating->update(['status' => $validated['status']]);

        return redirect()->route('admin.product-ratings.show', $productRating)
            ->with('status', $validated['status'] === 'published' ? 'Avis publié.' : 'Avis masqué du catalogue public.');
    }
}
