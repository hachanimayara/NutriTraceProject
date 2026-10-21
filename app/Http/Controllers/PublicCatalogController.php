<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    public function home()
    {
        $products = $this->publicProducts()
            ->select(['id', 'user_id', 'farm_id', 'name', 'reference', 'category', 'origin', 'description', 'quantity', 'unit', 'status', 'image_name', 'image_mime', 'created_at'])
            ->with(['farm:id,name,location,country', 'lots' => fn ($query) => $query->where('status', 'active')->select('id', 'product_id', 'public_id', 'code', 'status')])
            ->latest()
            ->limit(4)
            ->get();

        return view('front.home', compact('products'));
    }

    public function index(Request $request)
    {
        $products = $this->publicProducts()
            ->select(['id', 'user_id', 'farm_id', 'name', 'reference', 'category', 'origin', 'description', 'quantity', 'unit', 'status', 'image_name', 'image_mime', 'created_at'])
            ->with(['farm:id,name,location,country', 'lots' => fn ($query) => $query->where('status', 'active')->select('id', 'product_id', 'public_id', 'code', 'status'), 'certifications' => fn ($query) => $query->where('status', 'valid')->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today())), 'indicators' => fn ($query) => $query->where('status', 'valid')->where('metric_code', 'like', '%CO2%')])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim($request->string('q')->toString()).'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('category', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhere('origin', 'like', $term)
                    ->orWhereHas('farm', fn ($farm) => $farm->where('name', 'like', $term)->orWhere('location', 'like', $term)));
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->filled('origin'), fn ($query) => $query->where(fn ($origin) => $origin
                ->where('origin', $request->string('origin')->toString())
                ->orWhereHas('farm', fn ($farm) => $farm->where('location', $request->string('origin')->toString()))))
            ->latest()
            ->paginate(5)
            ->withQueryString();

        $categories = $this->publicProducts()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $origins = Product::query()
            ->where('products.status', 'published')
            ->whereHas('owner', fn ($query) => $query->where('status', 'approved'))
            ->leftJoin('farms as catalog_farms', function ($join) {
                $join->on('products.farm_id', '=', 'catalog_farms.id')->whereNull('catalog_farms.deleted_at');
            })
            ->selectRaw("COALESCE(NULLIF(products.origin, ''), NULLIF(catalog_farms.location, '')) as resolved_origin")
            ->whereRaw("COALESCE(NULLIF(products.origin, ''), NULLIF(catalog_farms.location, '')) IS NOT NULL")
            ->distinct()
            ->orderBy('resolved_origin')
            ->pluck('resolved_origin');

        return view('front.catalogue', compact('products', 'categories', 'origins'));
    }

    public function productImage(int $product)
    {
        $record = $this->publicProducts()->whereKey($product)->firstOrFail();
        abort_unless($record->image_contents && in_array($record->image_mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $contents = base64_decode($record->image_contents, true);
        abort_unless($contents !== false, 404);

        return response($contents, 200, [
            'Content-Type' => $record->image_mime,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function showProduct(int $product)
    {
        $record = $this->publicProducts()->whereKey($product)
            ->select(['id', 'user_id', 'farm_id', 'name', 'reference', 'category', 'origin', 'description', 'quantity', 'unit', 'status', 'image_name', 'image_mime', 'updated_at', 'created_at'])
            ->with($this->publicProductRelations())
            ->firstOrFail();

        return $this->renderPublicProduct($record);
    }

    public function showPublicProduct(string $publicId)
    {
        $record = $this->publicProducts()->where('public_id', $publicId)
            ->select(['id', 'user_id', 'farm_id', 'name', 'reference', 'category', 'origin', 'description', 'quantity', 'unit', 'status', 'image_name', 'image_mime', 'updated_at', 'created_at'])
            ->with($this->publicProductRelations())
            ->firstOrFail();

        return $this->renderPublicProduct($record);
    }

    private function publicProductRelations(): array
    {
        return [
            'farm:id,name,location,country',
            'owner:id,name',
            'farm.certifications' => fn ($query) => $query->where('status', 'valid')->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))->select(['id', 'farm_id', 'title', 'scheme', 'certificate_number', 'issuer', 'expires_at', 'status']),
            'lots' => fn ($query) => $query->where('status', 'active')->select(['id', 'product_id', 'code', 'quantity', 'unit', 'status', 'produced_at', 'created_at'])->with([
                'transformations' => fn ($transformations) => $transformations->select(['transformations.id', 'transformations.name', 'transformations.reference', 'transformations.performed_at', 'transformations.facility', 'transformations.status', 'transformations.created_at']),
                'distributions' => fn ($distributions) => $distributions->where('status', '!=', 'cancelled')->select(['id', 'lot_id', 'store_id', 'reference', 'quantity_shipped', 'quantity_delivered', 'unit', 'shipped_at', 'expected_at', 'delivered_at', 'status', 'created_at']),
                'distributions.store:id,name,city',
                'indicators' => fn ($indicators) => $indicators->where('status', 'valid')->select(['id', 'lot_id', 'name', 'value', 'unit', 'stage', 'measured_at', 'method', 'status']),
            ]),
            'certifications' => fn ($query) => $query->where('status', 'valid')->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))->select(['id', 'product_id', 'title', 'scheme', 'certificate_number', 'issuer', 'expires_at', 'status']),
            'indicators' => fn ($query) => $query->where('status', 'valid')->select(['id', 'product_id', 'name', 'value', 'unit', 'stage', 'measured_at', 'method', 'status']),
        ];
    }

    private function renderPublicProduct(Product $record)
    {
        /*
         * This method deliberately renders only the public fields already consumed by the
         * traceability view. Private owner attributes and record notes are not serialized.
         */
        $events = collect();
        $certifications = $record->certifications->concat($record->farm?->certifications ?? collect())->unique('id')->values();
        $indicators = $record->indicators->concat($record->lots->flatMap(fn ($lot) => $lot->indicators))->unique('id')->values();
        $record->setRelation('certifications', $certifications);
        $record->setRelation('indicators', $indicators);
        $publishedRatings = $record->ratings()->published();
        $ratingSummary = (clone $publishedRatings)->selectRaw('COUNT(*) as total, AVG(rating) as average')->first();
        $ratingDistribution = (clone $publishedRatings)->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');
        $ratings = (clone $publishedRatings)->with('consumer:id,name')->latest()->paginate(5);
        $ownRating = auth()->user()?->role === 'consommateur'
            ? $record->ratings()->where('consumer_id', auth()->id())->first()
            : (auth()->guest() ? $record->ratings()->where('guest_token', hash('sha256', request()->session()->getId()))->first() : null);
        $seenTransformations = [];
        foreach ($record->lots as $lot) {
            $events->push([
                'date' => $lot->produced_at ?? $lot->created_at,
                'title' => $lot->produced_at ? 'Production enregistrée' : 'Lot créé',
                'place' => $record->farm?->name ?: ($record->origin ?: 'Lieu non renseigné'),
                'description' => 'Lot '.$lot->code.' · '.number_format((float) $lot->quantity, 3, ',', ' ').' '.$lot->unit,
                'kind' => 'production',
            ]);

            foreach ($lot->transformations->unique('id') as $transformation) {
                if (isset($seenTransformations[$transformation->id])) {
                    continue;
                }
                $seenTransformations[$transformation->id] = true;

                $events->push([
                    'date' => $transformation->performed_at ?? $transformation->created_at,
                    'title' => $transformation->status === 'review'
                        ? 'Transformation à vérifier : '.($transformation->name ?: 'Opération enregistrée')
                        : ($transformation->name ?: 'Transformation enregistrée'),
                    'place' => $transformation->facility ?: 'Site non renseigné',
                    'description' => 'Opération enregistrée en lien avec le lot '.$lot->code.'.',
                    'kind' => 'transformation',
                ]);
            }

            foreach ($lot->distributions->where('status', '!=', 'cancelled') as $distribution) {
                $events->push([
                    'date' => $distribution->shipped_at ?? $distribution->created_at,
                    'title' => match ($distribution->status) {
                        'delivered' => 'Distribution livrée',
                        'delayed' => 'Distribution signalée en retard',
                        'in_transit' => 'Départ en distribution',
                        default => 'Distribution préparée',
                    },
                    'place' => trim(implode(', ', array_filter([$distribution->store?->name, $distribution->store?->city]))),
                    'description' => number_format((float) $distribution->quantity_shipped, 3, ',', ' ').' '.$distribution->unit.' expédiés · '.$distribution->reference,
                    'kind' => 'distribution',
                ]);
                if ($distribution->delivered_at) {
                    $events->push([
                        'date' => $distribution->delivered_at,
                        'title' => 'Réception enregistrée',
                        'place' => $distribution->store?->name ?: 'Point de vente non renseigné',
                        'description' => ($distribution->quantity_delivered !== null ? number_format((float) $distribution->quantity_delivered, 3, ',', ' ').' '.$distribution->unit.' reçus' : 'Réception déclarée').' · '.$distribution->reference,
                        'kind' => 'distribution',
                    ]);
                }
            }
        }
        $events = $events->sortBy(fn ($event) => $event['date']?->getTimestamp() ?? 0)->values();

        $completenessItems = [
            'Produit identifié' => filled($record->name) && filled($record->reference),
            'Origine renseignée' => filled($record->origin) || filled($record->farm?->location),
            'Exploitation associée' => filled($record->farm?->name),
            'Lot actif documenté' => $record->lots->isNotEmpty(),
            'Certification disponible' => $certifications->isNotEmpty(),
            'Indicateur environnemental' => $indicators->isNotEmpty(),
        ];
        $informationCount = count(array_filter($completenessItems));
        $completeness = (int) round($informationCount / count($completenessItems) * 100);

        return view('front.consumer-product-traceability', [
            'product' => $record,
            'events' => $events,
            'certifications' => $certifications,
            'indicators' => $indicators,
            'completenessItems' => $completenessItems,
            'informationCount' => $informationCount,
            'completeness' => $completeness,
            'ratings' => $ratings,
            'ownRating' => $ownRating,
            'ratingCount' => (int) ($ratingSummary->total ?? 0),
            'averageRating' => $ratingSummary->average,
            'ratingDistribution' => $ratingDistribution,
        ]);
    }

    public function compareProduct(Request $request, int $product)
    {
        $request->validate([
            'compare_with' => ['nullable', 'integer', 'different:product'],
        ]);

        $relations = [
            'farm',
            'lots' => fn ($query) => $query->where('status', 'active')->with(['transformations', 'distributions']),
            'certifications' => fn ($query) => $query->where('status', 'valid')->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today())),
            'indicators' => fn ($query) => $query->where('status', 'valid'),
        ];

        $currentProduct = $this->publicProducts()->whereKey($product)->with($relations)->firstOrFail();
        $otherProducts = $this->publicProducts()->whereKeyNot($currentProduct->id)
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'origin']);

        $selectedId = $request->integer('compare_with');
        if ($request->filled('compare_with')) {
            $comparisonProduct = $this->publicProducts()->whereKey($selectedId)->whereKeyNot($currentProduct->id)
                ->with($relations)
                ->firstOrFail();
        } else {
            $firstOtherId = $otherProducts->first()?->id;
            $comparisonProduct = $firstOtherId
                ? $this->publicProducts()->whereKey($firstOtherId)->with($relations)->first()
                : null;
        }

        $stepsCount = function (Product $record): int {
            $count = $record->lots->count();
            $transformationIds = collect();

            foreach ($record->lots as $lot) {
                $transformationIds = $transformationIds->merge($lot->transformations->pluck('id'));
                foreach ($lot->distributions->where('status', '!=', 'cancelled') as $distribution) {
                    $count += 1 + (int) (bool) $distribution->delivered_at;
                }
            }

            return $count + $transformationIds->unique()->count();
        };

        $indicatorValue = function (Product $record, array $keywords): string {
            $matches = $record->indicators->filter(function ($indicator) use ($keywords) {
                $search = strtolower($indicator->metric_code.' '.$indicator->name);

                foreach ($keywords as $keyword) {
                    if (str_contains($search, $keyword)) {
                        return true;
                    }
                }

                return false;
            });

            return $matches->isEmpty()
                ? 'Non renseigné'
                : $matches->map(fn ($indicator) => number_format((float) $indicator->value, 3, ',', ' ').' '.$indicator->unit.($indicator->stage ? ' · '.$indicator->stage : ''))->implode(' ; ');
        };

        $factsFor = function (Product $record) use ($stepsCount, $indicatorValue): array {
            return [
                'origin' => $record->origin ?: trim(implode(', ', array_filter([$record->farm?->location, $record->farm?->country]))) ?: 'Non renseignée',
                'producer' => $record->farm?->name ?: 'Non renseigné',
                'steps' => $stepsCount($record).' étape(s)',
                'certifications' => $record->certifications->count().' certification(s)',
                'carbon' => $indicatorValue($record, ['co2', 'carbon', 'carbone', 'ghg', 'gwp']),
                'water' => $indicatorValue($record, ['water', 'eau', 'h2o']),
                'transport' => $indicatorValue($record, ['transport', 'distance']),
            ];
        };

        $currentFacts = $factsFor($currentProduct);
        $comparisonFacts = $comparisonProduct ? $factsFor($comparisonProduct) : null;

        return view('front.product-comparison', compact('currentProduct', 'otherProducts', 'comparisonProduct', 'currentFacts', 'comparisonFacts'));
    }

    public function trace(string $lot)
    {
        $record = Lot::query()->where('public_id', $lot)->where('status', 'active')
            ->whereHas('product', fn ($query) => $query->where('status', 'published')->whereHas('owner', fn ($owner) => $owner->where('status', 'approved')))
            ->with(['product.farm', 'product.certifications' => fn ($query) => $query->where('status', 'valid')->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today())), 'transformations.lots.product', 'distributions.store'])
            ->firstOrFail();

        return view('front.traceability', ['lot' => $record]);
    }

    private function publicProducts()
    {
        return Product::query()
            ->where('status', 'published')
            ->whereHas('owner', fn ($query) => $query->where('status', 'approved'));
    }
}
