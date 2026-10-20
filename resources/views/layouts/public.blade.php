<!doctype html>
<html lang="fr" class="min-h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NutriTrace')</title>
    <meta name="description" content="@yield('description', 'Consultez les produits et leur traçabilité sur NutriTrace.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .public-site-nav{min-height:64px;padding:12px clamp(18px,5vw,72px);display:flex;align-items:center;justify-content:space-between;gap:20px;background:#fff;border-bottom:1px solid #e5ebe7;color:#173f30}
        .public-site-nav a{text-decoration:none;color:inherit}.public-site-brand{font-size:20px;font-weight:800;letter-spacing:-.03em}.public-site-links{display:flex;align-items:center;gap:22px;font-size:14px}.public-site-links .public-site-login{padding:9px 15px;border-radius:9px;background:#2d6a4f;color:#fff;font-weight:700}
        @media(max-width:540px){.public-site-nav{align-items:flex-start;flex-direction:column;gap:10px}.public-site-links{width:100%;justify-content:space-between;gap:10px;font-size:13px}}
    </style>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">
    <header class="public-site-nav">
        <a class="public-site-brand" href="{{ route('front.home') }}">NutriTrace</a>
        <nav class="public-site-links" aria-label="Navigation principale">
            <a href="{{ route('front.home') }}">Accueil</a>
            <a href="{{ route('public.catalogue') }}">Catalogue</a>
            <a class="public-site-login" href="{{ route('login') }}">Connexion</a>
        </nav>
    </header>
    <main>@yield('content')</main>
</body>
</html>
