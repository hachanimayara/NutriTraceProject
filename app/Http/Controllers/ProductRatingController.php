<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductRatingController extends Controller
{
    public function store(Request $request, int $product): RedirectResponse
    {
        if ($request->user() && ($request->user()->role !== 'consommateur' || $request->user()->status !== 'approved')) {
            abort(403);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'name' => ['nullable', 'string', 'max:80'],
            'website' => ['prohibited'],
        ], [
            'rating.required' => 'Choisissez une note entre 1 et 5 étoiles.',
            'rating.integer' => 'La note doit être un nombre entier.',
            'rating.between' => 'La note doit être comprise entre 1 et 5 étoiles.',
            'comment.max' => 'Votre commentaire ne peut pas dépasser 1 000 caractères.',
        ]);

        $productRecord = Product::query()
            ->where('status', 'published')
            ->whereHas('owner', fn ($query) => $query->where('status', 'approved'))
            ->findOrFail($product);

        $identity = $request->user()
            ? ['product_id' => $productRecord->id, 'consumer_id' => $request->user()->id]
            : ['product_id' => $productRecord->id, 'guest_token' => hash('sha256', $request->session()->getId())];
        $rating = ProductRating::query()->firstOrCreate($identity, [
            'rating' => $validated['rating'],
            'comment' => filled($validated['comment'] ?? null) ? trim($validated['comment']) : null,
            'consumer_name' => $request->user()?->name ?: (filled($validated['name'] ?? null) ? trim($validated['name']) : null),
            'status' => 'published',
        ]);

        return redirect()->route('public.products.show', $productRecord->id)
            ->with('status', $rating->wasRecentlyCreated
                ? 'Merci, votre avis a été enregistré.'
                : 'Vous avez déjà envoyé un avis pour ce produit. Il ne peut pas être modifié.');
    }
}
