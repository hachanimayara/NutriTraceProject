<?php

namespace App\Helpers;

class MenuHelper
{
    public static function getMenuGroups(): array
    {
        $items = [
            ['icon' => 'dashboard', 'name' => 'Tableau de bord', 'path' => auth()->check() ? auth()->user()->getDashboardUrl() : '/login'],
            ['icon' => 'website', 'name' => 'Site public', 'path' => '/'],
        ];
        if (auth()->check() && auth()->user()->isAdmin()) {
            $items[] = ['icon' => 'website', 'name' => 'Utilisateurs', 'path' => '/admin/users'];
            $items[] = ['icon' => 'website', 'name' => 'Alertes de traçabilité', 'path' => '/admin/alerts'];
            $items[] = ['icon' => 'website', 'name' => 'IA et reconnaissance de documents', 'path' => '/admin/document-analyses'];
        }
        if (auth()->check()) {
            $resources = match (auth()->user()->role) {
                'admin' => ['Exploitations' => 'farms', 'Produits' => 'products', 'Lots' => 'lots', 'Transformations' => 'transformations', 'Distributions' => 'distributions', 'Points de vente' => 'stores', 'Certifications' => 'certifications', 'Impact environnemental' => 'indicators'],
                'producteur' => ['Exploitations' => 'farms', 'Produits' => 'products', 'Certifications' => 'certifications', 'Impact environnemental' => 'indicators'],
                'transformateur' => ['Transformations' => 'transformations', 'Lots' => 'lots', 'Impact environnemental' => 'indicators'],
                'distributeur' => ['Distributions' => 'distributions', 'Points de vente' => 'stores', 'Lots' => 'lots', 'Impact environnemental' => 'indicators'],
                default => [],
            };
            foreach ($resources as $label => $resource) {
                $items[] = ['icon' => 'website', 'name' => $label, 'path' => '/workspace/'.$resource];
            }
            if (auth()->user()->role === 'transformateur') {
                $items[] = ['icon' => 'website', 'name' => 'Catalogue alimentaire et informations nutritionnelles', 'path' => '/workspace/open-food-facts'];
            }
            if (auth()->user()->role === 'distributeur') {
                $items[] = ['icon' => 'map', 'name' => 'Carte et distances', 'path' => '/workspace/map-distances'];
            }
            if (auth()->user()->role === 'producteur') {
                $items[] = ['icon' => 'qr', 'name' => 'QR Codes et traçabilité', 'path' => '/workspace/qr-codes'];
            }
        }
        $items[] = ['icon' => 'website', 'name' => 'Mon profil', 'path' => '/profile'];

        return [[
            'title' => 'Navigation',
            'items' => $items,
        ]];
    }

    public static function isActive(string $path): bool
    {
        return request()->path() === trim($path, '/');
    }

    public static function getIconSvg(string $iconName): string
    {
        return match ($iconName) {
            'qr' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v6h-6v-2"/><path d="M6 6h2m10 0h-2M6 16h2"/></svg>',
            'map' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15m6-12v15"/><circle cx="15" cy="9" r="1.5"/></svg>',
            'website' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
            default => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="5" rx="1"/><rect x="13" y="10" width="8" height="11" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/></svg>',
        };
    }
}
