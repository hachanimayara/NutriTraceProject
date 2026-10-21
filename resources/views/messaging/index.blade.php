@extends('layouts.admin')

@section('content')
<section class="messaging-page" data-messaging
    data-state-url="{{ route('messaging.state') }}"
    data-presence-url="{{ route('messaging.presence') }}"
    data-create-url="{{ route('messaging.conversations.store') }}"
    data-messages-url="{{ route('messaging.messages.index', ['conversation' => '__CONVERSATION__']) }}"
    data-send-url="{{ route('messaging.messages.store', ['conversation' => '__CONVERSATION__']) }}"
    data-current-user="{{ auth()->id() }}"
    data-open-conversation="{{ request('conversation') }}">
    <header class="messaging-page-header">
        <div><p class="messaging-kicker">Échanges professionnels</p><h1>Messagerie</h1><p>Conversations privées entre transformateurs et distributeurs.</p></div>
        <div class="messaging-connection" data-connection-status><span></span> Connexion à la messagerie</div>
    </header>

    <div class="messaging-shell">
        <aside class="messaging-inbox-pane" data-inbox-pane>
            <div class="messaging-inbox-heading"><h2>Conversations</h2><span data-inbox-count>0</span></div>
            <label class="messaging-search"><span class="sr-only">Rechercher une conversation, un message ou un utilisateur</span><input type="search" data-search placeholder="Rechercher un nom ou un message…" maxlength="100" autocomplete="off"><span aria-hidden="true">⌕</span></label>
            <div class="messaging-search-results" data-contact-results hidden></div>
            <div class="messaging-search-results" data-message-results hidden></div>
            <div class="messaging-conversation-list" data-conversation-list aria-live="polite"><p class="messaging-empty">Chargement des conversations…</p></div>
            <section class="messaging-directory" data-directory>
                <h3>{{ auth()->user()->role === 'transformateur' ? 'Distributeurs dans NutriTrace' : 'Transformateurs dans NutriTrace' }}</h3>
                <div class="messaging-directory-list" data-directory-list aria-live="polite"><p class="messaging-empty">Chargement des interlocuteurs…</p></div>
            </section>
            <p class="messaging-inbox-note">La synchronisation se met à jour automatiquement lorsque cette page est ouverte.</p>
        </aside>

        <main class="messaging-thread-pane" data-thread-pane>
            <section class="messaging-placeholder" data-thread-placeholder><div class="messaging-placeholder-icon" aria-hidden="true">✉</div><h2>Vos échanges en un seul endroit</h2><p>Sélectionnez une conversation ou recherchez un interlocuteur pour commencer.</p></section>
            <div class="messaging-thread" data-thread hidden>
                <header class="messaging-thread-header" data-thread-header></header>
                <div class="messaging-network-error" data-thread-error role="status" hidden></div>
                <button type="button" class="messaging-load-older" data-load-older hidden>Charger les messages précédents</button>
                <div class="messaging-message-list" data-message-list aria-live="polite" aria-relevant="additions text"></div>
                <div class="messaging-typing" data-typing hidden></div>
                <form class="messaging-composer" data-composer>
                    <label class="sr-only" for="messaging-input">Votre message</label>
                    <textarea id="messaging-input" rows="1" maxlength="5000" placeholder="Écrire un message…" disabled></textarea>
                    <div class="messaging-composer-footer"><span>Entrée pour envoyer · Maj + Entrée pour une nouvelle ligne</span><button type="submit" disabled><span aria-hidden="true">➤</span> Envoyer</button></div>
                </form>
            </div>
        </main>
    </div>
    <div class="messaging-toasts" data-toasts aria-live="polite"></div>
</section>
@endsection
