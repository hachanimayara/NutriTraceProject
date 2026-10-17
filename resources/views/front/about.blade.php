@extends('layouts.front')

@section('title', 'À propos | NutriTrace')
@section('description', 'Découvrez le projet NutriTrace et sa mission.')

@section('content')
<section class="container-fluid page-header mb-5 wow fadeIn" data-wow-delay="0.1s">
    <div class="container"><h1 class="display-3 mb-3 animated slideInDown">À propos de NutriTrace</h1><nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('front.home') }}">Accueil</a></li><li class="breadcrumb-item active" aria-current="page">À propos</li></ol></nav></div>
</section>
<section class="container-xxl py-5"><div class="container"><div class="row g-5 align-items-center">
    <div class="col-lg-6"><img class="img-fluid rounded" src="{{ asset('themes/foody/img/about.jpg') }}" alt="Produits frais"></div>
    <div class="col-lg-6"><h2 class="display-5 mb-4">Une information nutritionnelle plus accessible</h2><p class="mb-4">NutriTrace est un projet qui vise à présenter les informations et outils liés à l’alimentation dans une expérience claire, pratique et accessible.</p><p>Le site public et l’espace d’administration reposent sur Laravel 12 et Blade. Les données du tableau de bord restent des exemples tant que les fonctionnalités métier correspondantes ne sont pas définies.</p><a href="{{ route('front.home') }}" class="btn btn-primary rounded-pill py-3 px-5 mt-3">Retour à l’accueil</a></div>
</div></div></section>
@endsection
