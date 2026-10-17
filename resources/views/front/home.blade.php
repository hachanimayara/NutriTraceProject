@extends('layouts.front')

@section('title', 'Accueil | NutriTrace')
@section('description', 'NutriTrace vous aide à mieux comprendre votre alimentation grâce à des repères clairs et accessibles.')

@section('content')
<div class="container-fluid p-0 mb-5">
    <div id="header-carousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img class="w-100" src="{{ asset('themes/foody/img/carousel-1.jpg') }}" alt="Produits frais et alimentation naturelle">
                <div class="carousel-caption"><div class="container"><div class="row justify-content-start"><div class="col-lg-7">
                    <h1 class="display-2 mb-5 animated slideInDown">Mieux manger commence par mieux comprendre.</h1>
                    <a href="#approche" class="btn btn-primary rounded-pill py-sm-3 px-sm-5">Notre approche</a>
                    <a href="{{ route('front.about') }}" class="btn btn-secondary rounded-pill py-sm-3 px-sm-5 ms-3">À propos</a>
                </div></div></div></div>
            </div>
            <div class="carousel-item">
                <img class="w-100" src="{{ asset('themes/foody/img/carousel-2.jpg') }}" alt="Une alimentation équilibrée au quotidien">
                <div class="carousel-caption"><div class="container"><div class="row justify-content-start"><div class="col-lg-7">
                    <h1 class="display-2 mb-5 animated slideInDown">Des choix plus éclairés, à votre rythme.</h1>
                    <a href="{{ route('front.about') }}" class="btn btn-primary rounded-pill py-sm-3 px-sm-5">Découvrir NutriTrace</a>
                </div></div></div></div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#header-carousel" data-bs-slide="prev" aria-label="Précédent"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
        <button class="carousel-control-next" type="button" data-bs-target="#header-carousel" data-bs-slide="next" aria-label="Suivant"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
    </div>
</div>

<section class="container-xxl py-5" id="approche"><div class="container"><div class="row g-5 align-items-center">
    <div class="col-lg-6 wow fadeIn"><div class="about-img position-relative overflow-hidden p-5 pe-0"><img class="img-fluid w-100" src="{{ asset('themes/foody/img/about.jpg') }}" alt="Sélection de produits frais"></div></div>
    <div class="col-lg-6 wow fadeIn"><h2 class="display-5 mb-4">Une approche simple de la nutrition</h2><p class="mb-4">NutriTrace a pour vocation de rendre les informations sur l’alimentation plus accessibles et de vous accompagner dans vos choix du quotidien.</p>
        <p><i class="fa fa-check text-primary me-3"></i>Des informations présentées clairement</p><p><i class="fa fa-check text-primary me-3"></i>Des repères pensés pour le quotidien</p><p><i class="fa fa-check text-primary me-3"></i>Une expérience simple à parcourir</p>
        <a class="btn btn-primary rounded-pill py-3 px-5 mt-3" href="{{ route('front.about') }}">En savoir plus</a>
    </div>
</div></div></section>

<section class="container-fluid bg-light bg-icon my-5 py-6"><div class="container"><div class="section-header text-center mx-auto mb-5 wow fadeInUp" style="max-width: 600px"><h2 class="display-5 mb-3">Les repères NutriTrace</h2><p>Une plateforme conçue pour vous aider à avancer avec clarté.</p></div>
    <div class="row g-4">
        <div class="col-lg-4 col-md-6 wow fadeInUp"><div class="bg-white text-center h-100 p-4 p-xl-5"><img class="img-fluid mb-4" src="{{ asset('themes/foody/img/icon-1.png') }}" alt=""><h3 class="h4 mb-3">Comprendre</h3><p class="mb-0">Retrouvez des informations utiles dans une interface claire et accessible.</p></div></div>
        <div class="col-lg-4 col-md-6 wow fadeInUp"><div class="bg-white text-center h-100 p-4 p-xl-5"><img class="img-fluid mb-4" src="{{ asset('themes/foody/img/icon-2.png') }}" alt=""><h3 class="h4 mb-3">S’organiser</h3><p class="mb-0">Rassemblez les repères qui facilitent vos choix alimentaires au quotidien.</p></div></div>
        <div class="col-lg-4 col-md-6 wow fadeInUp"><div class="bg-white text-center h-100 p-4 p-xl-5"><img class="img-fluid mb-4" src="{{ asset('themes/foody/img/icon-3.png') }}" alt=""><h3 class="h4 mb-3">Progresser</h3><p class="mb-0">Explorez une expérience pensée pour accompagner vos habitudes dans la durée.</p></div></div>
    </div>
</div></section>
@endsection
