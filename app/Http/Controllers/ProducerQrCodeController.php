<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProducerQrCodeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $products = Product::query()
            ->where('user_id', $request->user()->id)
            ->when($filters['q'] ?? null, function ($query, string $term) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';
                $query->where(fn ($search) => $search->where('name', 'like', $term)->orWhere('reference', 'like', $term));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = Product::query()->where('user_id', $request->user()->id)
            ->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category');
        $baseUrl = rtrim((string) config('app.public_url'), '/');
        $publicUrlReady = $this->isSafePublicBaseUrl($baseUrl);

        return view('traceability.producer-qr-codes', compact('products', 'categories', 'baseUrl', 'publicUrlReady'));
    }

    private function isSafePublicBaseUrl(string $baseUrl): bool
    {
        $parts = parse_url($baseUrl);
        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }

        // QR codes are handed to consumers, so never encode a development URL,
        // even when the application itself is running outside production.
        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || preg_match('/\.(test|local|localhost|example|invalid|internal)$/', $host)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (app()->environment('production') && ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        return true;
    }
}
