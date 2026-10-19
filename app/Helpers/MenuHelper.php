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
        }
        $items[] = ['icon' => 'website', 'name' => 'Notifications', 'path' => '/notifications'];
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
            'website' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
            default => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="5" rx="1"/><rect x="13" y="10" width="8" height="11" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/></svg>',
        };
    }
}
