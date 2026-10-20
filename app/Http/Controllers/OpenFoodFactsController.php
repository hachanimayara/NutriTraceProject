<?php

namespace App\Http\Controllers;

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

    private const MANUAL = ['name', 'generic_name', 'brands', 'code', 'categories', 'quantity', 'ingredients', 'allergens', 'traces', 'nutrition', 'nutri_score', 'eco_score'];

    public function index(Request $request)
    {
        $products = Product::query()->where('status', 'published')->orderBy('name')->get(['id', 'name', 'reference', 'open_food_facts_data']);

        return view('traceability.open-food-facts', ['products' => $products, 'results' => [], 'detail' => null, 'message' => null]);
    }

    public function search(Request $request, OpenFoodFactsService $off)
    {
        $input = $request->validate(['query' => ['nullable', 'string', 'max:120', 'required_without:barcode'], 'barcode' => ['nullable', 'regex:/^[0-9]{8,14}$/', 'required_without:query']]);
        $products = Product::query()->where('status', 'published')->orderBy('name')->get(['id', 'name', 'reference', 'open_food_facts_data']);
        $results = [];
        $detail = null;
        $message = null;
        try {
            if (! empty($input['barcode'])) {
                $detail = $off->product($input['barcode']);
                if (! $detail) {
                    $message = 'Aucun produit trouvé pour ce code-barres. Vous pouvez saisir les informations manuellement.';
                }
            } else {
                $results = $off->search(trim($input['query']));
                if ($results === []) {
                    $message = 'Aucun résultat. Essayez un code-barres ou la saisie manuelle.';
                }
            }
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }

        return view('traceability.open-food-facts', compact('products', 'results', 'detail', 'message'))->with('query', $input['query'] ?? '')->with('barcode', $input['barcode'] ?? '');
    }

    public function import(Request $request, OpenFoodFactsService $off)
    {
        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1'], 'fields.*' => ['required', 'string', 'in:'.implode(',', self::IMPORTABLE)],
            'local_product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'published')],
            'barcode' => ['nullable', 'regex:/^[0-9]{8,14}$/'], 'manual' => ['nullable', 'array'],
            'manual.name' => ['nullable', 'string', 'max:160'], 'manual.generic_name' => ['nullable', 'string', 'max:255'],
            'manual.brands' => ['nullable', 'string', 'max:500'], 'manual.code' => ['nullable', 'regex:/^[0-9]{8,14}$/'],
            'manual.categories' => ['nullable', 'string', 'max:1000'], 'manual.quantity' => ['nullable', 'string', 'max:100'],
            'manual.ingredients' => ['nullable', 'string', 'max:5000'], 'manual.allergens' => ['nullable', 'string', 'max:1000'],
            'manual.traces' => ['nullable', 'string', 'max:1000'], 'manual.nutri_score' => ['nullable', 'string', 'max:10'],
            'manual.nutrition' => ['nullable', 'string', 'max:2000'],
            'manual.eco_score' => ['nullable', 'string', 'max:10'], 'overwrite' => ['nullable', 'boolean'], 'manual_mode' => ['nullable', 'boolean'],
        ]);
        $product = Product::where('status', 'published')->findOrFail($validated['local_product_id']);
        $fields = array_values(array_unique($validated['fields']));
        $manual = $request->boolean('manual_mode');
        if ($manual) {
            $incoming = array_filter(Arr::only($validated['manual'] ?? [], self::MANUAL), fn ($value) => $value !== null && $value !== '');
            $barcode = $incoming['code'] ?? null;
        } else {
            if (empty($validated['barcode'])) {
                throw ValidationException::withMessages(['barcode' => 'Recherchez d’abord un produit par code-barres.']);
            }
            try {
                $external = $off->product($validated['barcode']);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['api' => $e->getMessage()]);
            }
            if (! $external) {
                throw ValidationException::withMessages(['barcode' => 'Produit non trouvé. Utilisez la saisie manuelle.']);
            }
            $incoming = Arr::only($external, $fields);
            $barcode = $external['code'] ?: $validated['barcode'];
        }
        $incoming = Arr::only($incoming, $fields);
        $incoming = array_filter($incoming, fn ($value) => $value !== null && $value !== '' && $value !== []);
        if ($incoming === []) {
            throw ValidationException::withMessages(['fields' => 'Aucune information renseignée n’a été sélectionnée.']);
        }
        $current = $product->open_food_facts_data ?? [];
        $conflicts = array_intersect(array_keys($incoming), array_keys($current));
        if ($conflicts && ! $request->boolean('overwrite')) {
            throw ValidationException::withMessages(['overwrite' => 'Ces informations existent déjà. Confirmez explicitement leur remplacement avant de continuer.']);
        }
        $product->forceFill([
            'open_food_facts_data' => array_merge($current, $incoming),
            'open_food_facts_code' => $barcode ?: $product->open_food_facts_code,
            'open_food_facts_source' => $manual ? 'manual' : 'open_food_facts',
            'open_food_facts_imported_at' => now(),
        ])->save();

        return redirect()->route('workspace.open-food-facts.index')->with('status', 'Informations enregistrées dans la fiche « '.$product->name.' ». Les champs non sélectionnés et les lots n’ont pas été modifiés.');
    }
}
