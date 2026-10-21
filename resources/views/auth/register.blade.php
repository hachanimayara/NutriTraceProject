@extends('layouts.auth')
@section('title', 'Créer un compte · NutriTrace')
@section('content')
<div class="eyebrow">Créer votre espace</div>
<h1>Créer un compte</h1>
<p class="muted register-intro">Sélectionnez votre activité pour personnaliser votre expérience.</p>

@if($errors->any())<div class="alert warn">{{ $errors->first() }}</div>@endif

<form method="post" action="{{ route('register.store') }}" class="register-form" enctype="multipart/form-data">
    @csrf
    <div class="roles" role="radiogroup" aria-label="Sélectionnez votre activité">
        @php
            $activities = [
                'producteur' => ['Producteur', 'Exploitations et productions', 'tractor'],
                'transformateur' => ['Transformateur', 'Opérations de transformation', 'factory'],
                'distributeur' => ['Distributeur', 'Lots et mouvements', 'truck'],
            ];
        @endphp
        @foreach($activities as $value => [$label, $description, $icon])
            <label class="role role-{{ $value }}">
                <input type="radio" name="role" value="{{ $value }}" @checked(old('role', 'producteur') === $value) required>
                <svg class="role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    @if($icon === 'tractor')<path d="M3 17h13l2-5h3l1 5h-2M5 17v2m11-2v2M7 17l1-6h5l2 6M3 11h5M8 11V8h4l2 3"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>
                    @elseif($icon === 'factory')<path d="M3 21V9l7 4V9l7 4V5h4v16H3Z"/><path d="M7 17h1m4 0h1m4 0h1M17 8h1"/>
                    @elseif($icon === 'truck')<path d="M2 6h12v12H2zM14 10h4l4 4v4h-8z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>
                    @else<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
                    @endif
                </svg>
                <span class="role-copy"><b>{{ $label }}</b><small>{{ $description }}</small></span>
                <svg class="role-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
            </label>
        @endforeach
    </div>
    @error('role')<div class="error">{{ $message }}</div>@enderror


    <div class="grid">
        <div class="field"><label for="name">Nom complet</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" placeholder="Votre nom" required>@error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="phone">Téléphone</label><div class="phone-input"><span>+216</span><input id="phone" name="phone" inputmode="numeric" maxlength="8" pattern="[0-9]{8}" value="{{ old('phone') }}" placeholder="12345678" aria-describedby="phone-help" required></div><small id="phone-help" class="hint">8 chiffres, sans indicatif</small>@error('phone')<div class="error">{{ $message }}</div>@enderror</div>
    </div>
    <div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" placeholder="vous@exemple.fr" required>@error('email')<div class="error">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" placeholder="12 caractères minimum" required><small class="hint">12 caractères minimum, majuscule, minuscule, chiffre et symbole</small></div>
    <div class="field"><label for="password_confirmation">Confirmer le mot de passe</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Saisissez-le à nouveau" required></div>
    <div class="field"><label for="role_document">Justificatif de votre activité *</label><input id="role_document" name="role_document" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required aria-describedby="role-document-help"><small id="role-document-help" class="hint">Déposez un document qui permet à l’administration de vérifier votre activité (PDF, JPG, PNG ou WEBP · 5 Mo maximum).</small>@error('role_document')<div class="error">{{ $message }}</div>@enderror</div>
    <label class="terms"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))> <span>J’accepte les conditions d’utilisation et la politique de confidentialité.</span></label>
    @error('terms')<div class="error">{{ $message }}</div>@enderror
    <div class="captcha">@if(config('services.google_recaptcha.enabled') && config('services.google_recaptcha.site_key'))<div class="g-recaptcha" data-sitekey="{{ config('services.google_recaptcha.site_key') }}"></div>@else<div class="captcha-note">Ajoutez les clés reCAPTCHA v2 dans le fichier .env pour activer l’inscription.</div>@endif @error('captcha')<div class="error">{{ $message }}</div>@enderror</div>
    <button class="btn">Créer mon compte</button>
</form>
<script>
    (() => {
        const help = document.getElementById('role-document-help');
        const examples = {
            producteur: 'Producteur : carte agricole, attestation d’exploitation ou document équivalent.',
            transformateur: 'Transformateur : patente, extrait du registre de commerce ou autorisation d’activité.',
            distributeur: 'Distributeur : patente, extrait du registre de commerce ou autorisation de distribution.'
        };
        document.querySelectorAll('input[name="role"]').forEach(input => input.addEventListener('change', () => {
            if (input.checked && help) help.textContent = `${examples[input.value]} (PDF, JPG, PNG ou WEBP · 5 Mo maximum).`;
        }));
        const selected = document.querySelector('input[name="role"]:checked');
        if (selected && help) help.textContent = `${examples[selected.value]} (PDF, JPG, PNG ou WEBP · 5 Mo maximum).`;
    })();
</script>
<p class="muted register-login">Déjà inscrit ? <a class="back" href="{{ route('login') }}">Se connecter</a></p>
<style>
    .register-form .role-distributeur{grid-column:1 / -1;width:calc((100% - 9px) / 2);justify-self:center}.register-form .field input[type=file]{height:auto;min-height:48px;padding:7px 10px;font-size:12px;line-height:1.4;cursor:pointer}.register-form .field input[type=file]::file-selector-button{height:32px;margin-right:10px;padding:0 12px;border:1px solid #d3ded7;border-radius:8px;background:#f0f7f2;color:#1b4332;font:600 12px Arial,sans-serif;cursor:pointer}.register-form .field input[type=file]::file-selector-button:hover{background:#e4f1e8}
    @media(max-width:560px){.register-form .role-distributeur{width:100%}}
</style>
@endsection
