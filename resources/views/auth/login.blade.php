@extends('layouts.auth')
@section('title','Connexion · NutriTrace')
@section('content')<div class="eyebrow">Accès sécurisé</div><h1>Connexion</h1><p class="muted">Accédez à votre espace NutriTrace.</p>
@if(session('status'))<div class="alert">{{ session('status') }}</div>@endif @if(session('warning'))<div class="alert warn">{{ session('warning') }}</div>@endif
@if($errors->any())<div class="alert warn">{{ $errors->first() }}</div>@endif
<form id="login-form" method="post" action="{{ route('login.store') }}">@csrf
<div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<div class="error">{{ $message }}</div>@enderror</div>
<div class="field"><label for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
<div class="links"><a class="back" href="{{ route('password.request') }}">Mot de passe oublié ?</a></div>
<div class="captcha">@if(config('services.google_recaptcha.enabled') && config('services.google_recaptcha.site_key'))<div class="g-recaptcha" data-sitekey="{{ config('services.google_recaptcha.site_key') }}"></div>@else<div class="captcha-note">Ajoutez les clés reCAPTCHA v2 dans le fichier .env pour activer la connexion.</div>@endif @error('captcha')<div class="error">{{ $message }}</div>@enderror</div>
<button class="btn" id="login-submit" type="submit">Se connecter</button></form>
<script>
document.getElementById('login-form')?.addEventListener('submit', function () {
    const button = document.getElementById('login-submit');
    if (button) {
        button.disabled = true;
        button.textContent = 'Connexion en cours…';
    }
});
</script>
<div style="margin:14px 0"><a class="google-button" href="{{ route('google.redirect') }}" aria-label="Continuer avec Google"><svg aria-hidden="true" viewBox="0 0 48 48" width="18" height="18"><path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.8h11a9.4 9.4 0 0 1-4.1 6.2v5.1h6.6c3.9-3.6 6.1-8.8 6.1-15z"/><path fill="#34A853" d="M24 44c5.5 0 10.1-1.8 13.5-4.8l-6.6-5.1c-1.8 1.2-4 1.9-6.9 1.9-5.3 0-9.8-3.6-11.4-8.4H5.8v5.3A20 20 0 0 0 24 44z"/><path fill="#FBBC05" d="M12.6 27.6a12 12 0 0 1 0-7.2v-5.3H5.8a20 20 0 0 0 0 17.8z"/><path fill="#EA4335" d="M24 12c3 0 5.7 1 7.8 3.1l5.9-5.9C34.1 6 29.5 4 24 4A20 20 0 0 0 5.8 15.1l6.8 5.3C14.2 15.6 18.7 12 24 12z"/></svg><span>Continuer avec Google</span></a></div><div style="margin:14px 0">
    <button type="button" class="secondary" id="passkey-login" style="width:100%">Connexion biométrique (Face ID / appareil)</button>
    <p class="small" style="margin:8px 0;text-align:center">Selon votre appareil, confirmez avec Face ID, Windows Hello, un code ou une clé d’accès. NutriTrace ne reçoit aucune donnée biométrique.</p>
    <div id="passkey-login-status" class="alert warn" role="status" aria-live="polite" hidden></div>
</div>
<p class="register-login">Pas encore de compte NutriTrace ? <a class="back" href="{{ route('register') }}">Créer un compte</a></p>
@endsection
