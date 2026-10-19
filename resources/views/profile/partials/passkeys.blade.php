@php($passkeys = $user->passkeys)

<div class="profile-section-head">
    <span class="profile-section-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3H5a2 2 0 0 0-2 2v3m13-5h3a2 2 0 0 1 2 2v3M3 16v3a2 2 0 0 0 2 2h3m13-5v3a2 2 0 0 1-2 2h-3"/><circle cx="9" cy="10" r="1"/><circle cx="15" cy="10" r="1"/><path d="M8 15c1 2 7 2 8 0"/></svg>
    </span>
    <div>
        <h2>Sécurité et reconnaissance faciale</h2>
        <p>Utilisez la biométrie ou le code de votre appareil grâce à une clé d’accès sécurisée.</p>
    </div>
</div>

<div class="profile-face-content">
    <div class="face-illustration" aria-hidden="true">
        <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8h-8a5 5 0 0 0-5 5v8m35-13h8a5 5 0 0 1 5 5v8M8 43v8a5 5 0 0 0 5 5h8m35-13v8a5 5 0 0 1-5 5h-8"/><circle cx="24" cy="27" r="2" fill="currentColor"/><circle cx="40" cy="27" r="2" fill="currentColor"/><path d="M22 39c5 7 15 7 20 0"/></svg>
    </div>

    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold {{ $passkeys->isNotEmpty() ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700' }}">
        <span class="h-1.5 w-1.5 rounded-full {{ $passkeys->isNotEmpty() ? 'bg-green-700' : 'bg-gray-400' }}"></span>
        {{ $passkeys->isNotEmpty() ? 'Configurée' : 'Non configurée' }}
    </span>

    <h3 class="mt-4">{{ $passkeys->isNotEmpty() ? 'Gérez vos clés biométriques' : 'Configurez une clé d’accès' }}</h3>
    <p>Face ID, Windows Hello ou votre méthode de sécurité locale confirme votre identité. NutriTrace ne reçoit ni ne stocke de photo ou de modèle de votre visage.</p>
    @if($passkeys->isNotEmpty())<p class="mt-2 text-xs text-gray-500">« Modifier mon visage » ajoute une nouvelle clé d’accès. Vos clés actuelles restent valides jusqu’à leur révocation.</p>@endif

    <div class="mx-auto grid max-w-lg gap-3 text-left">
        <label class="profile-field">Nom de cet appareil
            <input id="passkey-name" type="text" maxlength="80" value="Mon appareil" autocomplete="off" placeholder="Ex. Mon téléphone">
        </label>
        <label class="flex cursor-pointer items-start gap-2 text-left text-xs leading-5 text-gray-600">
            <input id="passkey-consent" type="checkbox" class="mt-1 accent-green-800">
            <span>Je consens à utiliser la biométrie ou le code de mon appareil pour confirmer la clé d’accès. Seule sa clé publique est conservée par NutriTrace.</span>
        </label>
    </div>

    <button class="profile-action mx-auto mt-4" type="button" id="add-passkey" data-consent-url="{{ route('profile.passkeys.consent') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="18" height="14" rx="2"/><circle cx="12" cy="14" r="3"/><path d="M8 7 9.5 4h5L16 7"/></svg>
        {{ $passkeys->isNotEmpty() ? 'Modifier mon visage' : 'Configurer Face ID' }}
    </button>
    <p id="passkey-status" class="profile-message" role="status" aria-live="polite" hidden></p>
</div>

@if($passkeys->isNotEmpty())
    <div class="profile-passkeys">
        <h3 class="mb-2 text-sm font-semibold text-gray-800">Appareils configurés</h3>
        <ul>
            @foreach($passkeys as $passkey)
                <li>
                    <span>
                        <strong class="block">{{ $passkey->name }}</strong>
                        <small class="text-gray-500">Ajouté le {{ $passkey->created_at?->format('d/m/Y') ?? '—' }}{{ $passkey->last_used_at ? ' · Utilisé le '.$passkey->last_used_at->format('d/m/Y') : '' }}</small>
                    </span>
                    <form method="post" action="{{ route('passkey.destroy', $passkey) }}" onsubmit="return confirm('Révoquer cette clé d’accès ?')">
                        @csrf @method('DELETE')
                        <button type="submit" aria-label="Révoquer {{ $passkey->name }}">Révoquer</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>

    <form method="post" action="{{ route('profile.passkeys.disable') }}" class="profile-note" onsubmit="return confirm('Désactiver la connexion biométrique et supprimer toutes les clés d’accès de ce compte ?')">
        @csrf @method('DELETE')
        <strong class="block text-red-800">Désactiver la reconnaissance faciale</strong>
        <span class="mt-1 block">Toutes les clés d’accès NutriTrace seront supprimées. Vous pourrez toujours vous connecter avec votre adresse e-mail et votre mot de passe.</span>
        <button class="mt-3 rounded-lg border border-red-200 bg-white px-4 py-2 text-xs font-semibold text-red-700 hover:bg-red-50" type="submit">Désactiver</button>
    </form>
@endif
