<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenFoodFactsService
{
    private const FIELDS = 'code,product_name,generic_name,brands,categories,quantity,ingredients_text,allergens,traces,nutriments,nutrition_grades,ecoscore_grade,image_url,image_front_url';

    public function product(string $barcode): ?array
    {
        return Cache::remember('off:product:'.$barcode, now()->addHours(12), function () use ($barcode) {
            try {
                $response = Http::withUserAgent(config('services.open_food_facts.user_agent'))
                    ->acceptJson()->timeout(8)->connectTimeout(4)
                    ->get(rtrim(config('services.open_food_facts.product_url'), '/').'/'.rawurlencode($barcode), [
                        'fields' => self::FIELDS, 'lc' => 'fr', 'cc' => 'tn',
                    ]);
            } catch (ConnectionException) {
                throw new RuntimeException('Le catalogue Open Food Facts est temporairement inaccessible. Vous pouvez continuer en saisie manuelle.');
            }

            if ($response->status() === 404 || ($response->successful() && (string) $response->json('status') === 'failure')) {
                return null;
            }
            if ($response->status() === 429) {
                throw new RuntimeException('Open Food Facts limite temporairement les recherches. Réessayez plus tard ou saisissez les informations manuellement.');
            }
            if (! $response->successful()) {
                throw new RuntimeException('La recherche Open Food Facts a échoué. Vous pouvez continuer en saisie manuelle.');
            }
            $product = $response->json('product');
            if (! is_array($product)) {
                throw new RuntimeException('La réponse du catalogue est invalide. Vous pouvez continuer en saisie manuelle.');
            }

            return $this->normalize($product, $barcode);
        });
    }

    public function search(string $term): array
    {
        return Cache::remember('off:search:'.hash('sha256', $term), now()->addMinutes(5), function () use ($term) {
            try {
                $response = Http::withUserAgent(config('services.open_food_facts.user_agent'))
                    ->acceptJson()->timeout(8)->connectTimeout(4)
                    ->post(config('services.open_food_facts.search_url'), [
                        'q' => $term, 'langs' => ['fr', 'en'], 'page' => 1, 'page_size' => 8,
                        'fields' => explode(',', self::FIELDS),
                    ]);
            } catch (ConnectionException) {
                throw new RuntimeException('Le catalogue Open Food Facts est temporairement inaccessible. Vous pouvez continuer en saisie manuelle.');
            }
            if ($response->status() === 429) {
                throw new RuntimeException('Open Food Facts limite temporairement les recherches. Réessayez plus tard ou saisissez les informations manuellement.');
            }
            if (! $response->successful()) {
                throw new RuntimeException('La recherche Open Food Facts a échoué. Vous pouvez continuer en saisie manuelle.');
            }

            $body = $response->json();
            if (! is_array($body)) {
                throw new RuntimeException('La réponse du catalogue est invalide. Vous pouvez continuer en saisie manuelle.');
            }
            $items = $body['hits'] ?? $body['products'] ?? [];

            return collect(is_array($items) ? $items : [])->map(function ($hit) {
                $data = $hit['_source'] ?? $hit['product'] ?? $hit;

                return is_array($data) ? $this->normalize($data, (string) ($data['code'] ?? '')) : null;
            })->filter(fn ($item) => $item && ($item['code'] !== '' || $item['name'] !== ''))->values()->all();
        });
    }

    private function normalize(array $p, string $barcode): array
    {
        $nutriments = is_array($p['nutriments'] ?? null) ? $p['nutriments'] : [];
        $value = static fn (string $key, string $unit): ?string => isset($nutriments[$key.'_100g'])
            ? (string) $nutriments[$key.'_100g'].' '.$unit
            : null;
        $nutrition = array_filter([
            'energy' => $value('energy-kcal', 'kcal'), 'fat' => $value('fat', 'g'),
            'saturated_fat' => $value('saturated-fat', 'g'), 'carbohydrates' => $value('carbohydrates', 'g'),
            'sugars' => $value('sugars', 'g'), 'proteins' => $value('proteins', 'g'), 'salt' => $value('salt', 'g'),
        ], fn ($item) => $item !== null);

        return [
            'code' => $this->text($p['code'] ?? $barcode), 'name' => $this->text($p['product_name'] ?? ''),
            'generic_name' => $this->text($p['generic_name'] ?? ''), 'brands' => $this->text($p['brands'] ?? ''),
            'categories' => $this->text($p['categories'] ?? ''), 'quantity' => $this->text($p['quantity'] ?? ''),
            'ingredients' => $this->text($p['ingredients_text'] ?? ''), 'allergens' => $this->text($p['allergens'] ?? ''),
            'traces' => $this->text($p['traces'] ?? ''), 'nutrition' => $nutrition,
            'nutri_score' => $this->text($p['nutrition_grades'] ?? ''), 'eco_score' => $this->text($p['ecoscore_grade'] ?? ''),
            'image' => $this->text($p['image_front_url'] ?? $p['image_url'] ?? ''),
        ];
    }

    private function text(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return '';
        }

        foreach (['fr', 'en'] as $language) {
            if (isset($value[$language])) {
                return $this->text($value[$language]);
            }
        }

        return collect($value)
            ->map(fn ($item) => $this->text($item))
            ->filter(fn (string $item) => $item !== '')
            ->unique()
            ->implode(', ');
    }
}
