<?php

namespace App\Helpers;

use App\Models\ChatMessage;

class MenuHelper
{
    public static function getMenuGroups(): array
    {
        $items = [
            ['icon' => 'dashboard', 'name' => 'Tableau de bord', 'path' => auth()->check() ? auth()->user()->getDashboardUrl() : '/login'],
        ];
        if (auth()->check() && auth()->user()->isAdmin()) {
            $items[] = ['icon' => 'website', 'name' => 'Conformité environnementale', 'path' => '/admin/compliance'];
            $items[] = ['icon' => 'website', 'name' => 'Avis consommateurs', 'path' => '/admin/product-ratings'];
            $items[] = ['icon' => 'website', 'name' => 'Utilisateurs', 'path' => '/admin/users'];
            $items[] = ['icon' => 'website', 'name' => 'IA et reconnaissance de documents', 'path' => '/admin/document-analyses'];
        }
        if (auth()->check()) {
            $resources = match (auth()->user()->role) {
                'admin' => ['Exploitations' => 'farms', 'Produits' => 'products', 'Lots' => 'lots', 'Transformations' => 'transformations', 'Distributions' => 'distributions', 'Points de vente' => 'stores', 'Certifications' => 'certifications', 'Impact environnemental' => 'indicators'],
                'producteur' => ['Exploitations' => 'farms', 'Produits' => 'products', 'Certifications' => 'certifications', 'Impact environnemental' => 'indicators'],
                'transformateur' => ['Transformations' => 'transformations', 'Lots' => 'lots', 'Certifications' => 'certifications', 'Impact environnemental' => 'indicators'],
                'distributeur' => ['Distributions' => 'distributions', 'Points de vente' => 'stores', 'Lots' => 'lots', 'Certifications' => 'certifications', 'Impact environnemental' => 'indicators'],
                default => [],
            };
            foreach ($resources as $label => $resource) {
                $items[] = ['icon' => 'website', 'name' => $label, 'path' => '/workspace/'.$resource];
            }
            if (auth()->user()->role === 'transformateur') {
                $items[] = ['icon' => 'website', 'name' => 'Catalogue alimentaire et informations nutritionnelles', 'path' => '/workspace/open-food-facts'];
            }
            if (auth()->user()->role === 'distributeur') {
                $items[] = ['icon' => 'history', 'name' => 'Historique', 'path' => '/workspace/history'];
                $items[] = ['icon' => 'map', 'name' => 'Carte et distances', 'path' => '/workspace/map-distances'];
            }
            if (in_array(auth()->user()->role, ['transformateur', 'distributeur'], true)) {
                $items[] = ['icon' => 'chat', 'name' => 'Messagerie', 'path' => '/messaging', 'unread_count' => ChatMessage::unreadCountFor(auth()->id())];
            }
        }
        if (auth()->check() && auth()->user()->role === 'producteur') {
            $items[] = ['icon' => 'calendar', 'name' => 'Planification des récoltes', 'path' => '/producer/harvest-plans'];
            $items[] = ['icon' => 'weather', 'name' => 'Météo agricole', 'path' => '/producer/weather'];
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
            'calendar' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18m-13 4h3m3 0h2m-8 3h3"/></svg>',
            'weather' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="3"/><path d="M8 1v2m0 10v2M1 8h2m10 0h2m-12-5 1.5 1.5m7 7L13 13m-10 0 1.5-1.5m7-7L13 3"/><path d="M7 18a3 3 0 0 1 2.8-3.99A4.5 4.5 0 0 1 18 16a2.5 2.5 0 0 1-.5 5H8a3 3 0 0 1-1-3Z"/></svg>',
            'chat' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/></svg>',
            'history' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5m4-1v5l3 2"/></svg>',
            'website' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
            default => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="5" rx="1"/><rect x="13" y="10" width="8" height="11" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/></svg>',
        };
    }
}
