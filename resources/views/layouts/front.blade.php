<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'NutriTrace')</title>
    <meta name="description" content="@yield('description', 'NutriTrace, votre repère pour une alimentation plus éclairée.')">
    <link rel="icon" href="{{ asset('themes/foody/img/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500&family=Lora:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('themes/foody/lib/animate/animate.min.css') }}" rel="stylesheet">
    <link href="{{ asset('themes/foody/lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">
    <link href="{{ asset('themes/foody/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('themes/foody/css/style.css') }}" rel="stylesheet">
    @vite('resources/js/app.js')
</head>
<body>
    <div class="container-fluid fixed-top px-0 wow fadeIn" data-wow-delay="0.1s">
        <div class="top-bar row gx-0 align-items-center d-none d-lg-flex">
            <div class="col-lg-6 px-5 text-start"><small>NutriTrace · mieux comprendre son alimentation</small></div>
            <div class="col-lg-6 px-5 text-end"><small>Un quotidien plus équilibré, pas à pas.</small></div>
        </div>
        <nav class="navbar navbar-expand-lg navbar-light py-lg-0 px-lg-5 wow fadeIn" data-wow-delay="0.1s">
            <a href="{{ route('front.home') }}" class="navbar-brand ms-4 ms-lg-0"><h1 class="fw-bold text-primary m-0">Nutri<span class="text-secondary">Trace</span></h1></a>
            <button type="button" class="navbar-toggler me-4" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-label="Ouvrir le menu"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <div class="navbar-nav ms-auto p-4 p-lg-0">
                    <a href="{{ route('front.home') }}" class="nav-item nav-link {{ request()->routeIs('front.home') ? 'active' : '' }}">Accueil</a>
                    <a href="{{ route('front.about') }}" class="nav-item nav-link {{ request()->routeIs('front.about') ? 'active' : '' }}">À propos</a>
                    <a href="{{ route('login') }}" class="nav-item nav-link {{ request()->routeIs('login') ? 'active' : '' }}">Connexion</a>
                </div>
                <div class="d-none d-lg-flex ms-2"><a class="btn btn-primary rounded-pill py-2 px-4" href="{{ route('front.about') }}">Découvrir NutriTrace</a></div>
            </div>
        </nav>
    </div>

    <main>@yield('content')</main>

    <div class="container-fluid bg-dark footer mt-5 pt-5 wow fadeIn" data-wow-delay="0.1s">
        <div class="container py-5"><div class="row g-5">
            <div class="col-lg-6 col-md-6"><h1 class="fw-bold text-primary mb-4">Nutri<span class="text-secondary">Trace</span></h1><p>Des repères simples pour mieux comprendre votre alimentation et avancer vers un meilleur équilibre.</p></div>
            <div class="col-lg-3 col-md-6"><h4 class="text-light mb-4">Navigation</h4><a class="btn btn-link" href="{{ route('front.home') }}">Accueil</a><a class="btn btn-link" href="{{ route('front.about') }}">À propos</a></div>
            <div class="col-lg-3 col-md-6"><h4 class="text-light mb-4">Espace gestion</h4><a class="btn btn-link" href="{{ route('admin.dashboard') }}">Tableau de bord</a></div>
        </div></div>
        <div class="container-fluid copyright"><div class="container"><div class="row"><div class="col-md-12 text-center mb-3 mb-md-0">&copy; {{ date('Y') }} NutriTrace. Tous droits réservés.</div></div></div></div>
    </div>
    <a href="#" class="btn btn-lg btn-primary btn-lg-square rounded-circle back-to-top" aria-label="Retour en haut"><i class="bi bi-arrow-up"></i></a>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('themes/foody/lib/wow/wow.min.js') }}"></script>
    <script src="{{ asset('themes/foody/lib/easing/easing.min.js') }}"></script>
    <script src="{{ asset('themes/foody/lib/waypoints/waypoints.min.js') }}"></script>
    <script src="{{ asset('themes/foody/lib/owlcarousel/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('themes/foody/js/main.js') }}"></script>
    @stack('scripts')
<script>
(() => {
    document.querySelectorAll('form[data-list-filters]').forEach((form) => {
        const search = form.querySelector('input[name="q"]');
        const selects = form.querySelectorAll('select');
        let timer;
        if (search) search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => form.requestSubmit(), 350);
        });
        selects.forEach((select) => select.addEventListener('change', () => { clearTimeout(timer); form.requestSubmit(); }));
    });
})();
</script>
</body>
</html>
