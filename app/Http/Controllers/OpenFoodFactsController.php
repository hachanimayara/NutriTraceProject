<?php

namespace App\Http\Controllers;

use App\Models\FoodCatalogItem;
use App\Models\Product;
use App\Services\OpenFoodFactsService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OpenFoodFactsController extends Controller
{
    private const IMPORTABLE = ['name', 'generic_name', 'brands', 'code', 'categories', 'quantity', 'ingredients', 'allergens', 'traces', 'nutrition', 'nutri_score', 'eco_score'];

    public function index(Request $request)
    {
        $items = FoodCatalogItem::query()->where('user_id', $request->user()->id)->latest()->paginate(5)->withQueryString();

        return view('traceability.open-food-facts', ['items' => $items, 'results' => [], 'detail' => null, 'message' => null]);
    }

    public function search(Request $request, OpenFoodFactsService $off)
    {
        $input = $request->validate([
            'query' => ['nullable', 'string', 'max:120', 'required_without:barcode'],
            'barcode' => ['nullable', 'regex:/^[0-9]{8,14}$/', 'required_without:query'],
        ]);
        $items = FoodCatalogItem::query()->where('user_id', $request->user()->id)->latest()->paginate(5)->withQueryString();
        $results = [];
        $detail = null;
        $message = null;

        try {
            if (! empty($input['barcode'])) {
                $detail = $off->product($input['barcode']);
                if (! $detail && ! empty($input['query'])) {
                    $results = $off->search(trim($input['query']));
                    $message = $results !== []
                        ? 'Aucun produit trouvé pour ce code-barres. Voici les résultats correspondant au nom saisi.'
                        : 'Aucun produit trouvé pour ce code-barres ni pour ce nom.';
                } elseif (! $detail) {
                    $message = 'Aucun produit trouvé pour ce code-barres.';
                }
            } else {
                $results = $off->search(trim($input['query']));
                if ($results === []) {
                    $message = 'Aucun résultat. Essayez un code-barres ou un autre nom.';
                }
            }
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }

        return view('traceability.open-food-facts', compact('items', 'results', 'detail', 'message'))
            ->with('query', $input['query'] ?? '')
            ->with('barcode', $input['barcode'] ?? '');
    }

    public function import(Request $request, OpenFoodFactsService $off)
    {
        if ($request->filled('local_product_id')) {
            $manual = $request->boolean('manual_mode');
            $validated = $request->validate([
                'local_product_id' => ['required', 'integer', Rule::exists('products', 'id')],
                'manual_mode' => ['nullable', 'boolean'],
                'barcode' => [Rule::requiredIf(! $manual), 'nullable', 'regex:/^[0-9]{8,14}$/'],
                'fields' => ['required', 'array', 'min:1'],
                'fields.*' => ['required', 'string', 'in:'.implode(',', self::IMPORTABLE)],
                'manual' => ['nullable', 'array'],
                'manual.*' => ['nullable', 'string', 'max:5000'],
                'overwrite' => ['nullable', 'boolean'],
            ]);
            $product = Product::query()->findOrFail($validated['local_product_id']);
            $existing = $product->open_food_facts_data ?? [];
            if ($existing !== [] && ! $request->boolean('overwrite')) {
                throw ValidationException::withMessages(['overwrite' => ['Des données existent déjà. Veuillez confirmer l’écrasement.']]);
            }

            if ($manual) {
                $external = $request->input('manual', []);
                $barcode = $validated['barcode'] ?? $product->open_food_facts_code;
            } else {
                try {
                    $external = $off->product($validated['barcode']);
                } catch (RuntimeException $e) {
                    throw ValidationException::withMessages(['api' => $e->getMessage()]);
                }
                if (! $external) {
                    throw ValidationException::withMessages(['barcode' => 'Produit non trouvé dans Open Food Facts.']);
                }
                $barcode = $external['code'] ?: $validated['barcode'];
            }

            $selected = array_filter(Arr::only($external, array_unique($validated['fields'])), fn ($value) => $value !== null && $value !== '' && $value !== []);
            if ($selected === []) {
                throw ValidationException::withMessages(['fields' => 'Aucune information renseignée n’a été sélectionnée.']);
            }
            $product->update([
                'open_food_facts_code' => $barcode,
                'open_food_facts_source' => $manual ? 'manual' : 'open_food_facts',
                'open_food_facts_imported_at' => now(),
                'open_food_facts_data' => array_merge($existing, $selected),
            ]);

            return redirect()->route('workspace.open-food-facts.index')->with('status', 'Les informations ont été ajoutées au produit.');
        }

        if ($request->boolean('manual_mode')) {
            $manual = $request->validate([
                'manual_mode' => ['required', 'boolean'],
                'manual' => ['required', 'array'],
                'manual.name' => ['required', 'string', 'max:160'],
                'manual.brands' => ['nullable', 'string', 'max:500'],
                'manual.categories' => ['nullable', 'string', 'max:500'],
                'manual.quantity' => ['nullable', 'string', 'max:120'],
                'manual.ingredients' => ['nullable', 'string', 'max:5000'],
                'barcode' => ['nullable', 'regex:/^[0-9]{8,14}$/'],
            ]);
            $data = array_filter($manual['manual'], fn ($value) => $value !== null && $value !== '');
            FoodCatalogItem::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'barcode' => $manual['barcode'] ?? null, 'source' => 'manual', 'data' => $data]);

            return redirect()->route('workspace.open-food-facts.index')->with('status', 'La fiche manuelle a été ajoutée au catalogue alimentaire.');
        }

        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', 'string', 'in:'.implode(',', self::IMPORTABLE)],
            'barcode' => ['required', 'regex:/^[0-9]{8,14}$/'],
        ]);

        try {
            $external = $off->product($validated['barcode']);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['api' => $e->getMessage()]);
        }

        if (! $external) {
            throw ValidationException::withMessages(['barcode' => 'Produit non trouvé dans Open Food Facts.']);
        }

        $incoming = array_filter(Arr::only($external, array_unique($validated['fields'])), fn ($value) => $value !== null && $value !== '' && $value !== []);
        if ($incoming === []) {
            throw ValidationException::withMessages(['fields' => 'Aucune information renseignée n’a été sélectionnée.']);
        }

        $name = trim((string) ($incoming['name'] ?? $external['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['fields' => 'Sélectionnez le nom du produit pour créer sa fiche catalogue.']);
        }

        $barcode = $external['code'] ?: $validated['barcode'];
        if (FoodCatalogItem::query()->where('user_id', $request->user()->id)->where('barcode', $barcode)->exists()) {
            throw ValidationException::withMessages(['barcode' => 'Ce code-barres est déjà enregistré dans votre catalogue alimentaire.']);
        }

        FoodCatalogItem::create([
            'user_id' => $request->user()->id,
            'name' => mb_substr($name, 0, 160),
            'barcode' => $barcode,
            'source' => 'open_food_facts',
            'data' => $incoming,
        ]);

        return redirect()->route('workspace.open-food-facts.index')->with('status', 'La fiche « '.$name.' » a été ajoutée à votre catalogue alimentaire.');
    }

    public function destroyData(Request $request, FoodCatalogItem $item)
    {
        abort_unless($item->user_id === $request->user()->id, 404);
        $item->delete();

        return redirect()->route('workspace.open-food-facts.index')->with('status', 'La fiche a été supprimée de votre catalogue alimentaire.');
    }
}
