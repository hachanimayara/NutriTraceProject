<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *{box-sizing:border-box}body{margin:0;padding:16px;background:#f8faf9;color:#26352d;font-family:inherit}.list{display:grid;gap:10px}.item{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;border:1px solid #e3e9e5;border-radius:12px;background:#fff;padding:14px}.item.unread{background:#f1f8f4}.message{font-size:14px;font-weight:650;line-height:1.5}.preview{margin-top:4px;color:#66756d;font-size:12px;line-height:1.5;overflow-wrap:anywhere}.time{display:block;margin-top:8px;color:#7b8981;font-size:11px}.actions{display:flex;flex:none;align-items:center;gap:10px}.actions button{border:0;background:transparent;color:#28684d;font:inherit;font-size:12px;font-weight:650;cursor:pointer}.delete{display:grid;width:34px;height:34px;place-items:center;border:1px solid #f0dada!important;border-radius:9px;color:#b42318!important}.delete svg{width:17px;height:17px}.pagination{margin-top:14px}.pagination nav{display:flex;justify-content:center}#notification-delete-dialog{position:fixed;inset:0;margin:auto;width:min(440px,calc(100vw - 28px));max-width:none;padding:0;border:1px solid #dce5df;border-radius:16px;background:#fff;box-shadow:0 24px 80px #10251b40}#notification-delete-dialog::backdrop{background:rgba(16,35,29,.42);backdrop-filter:blur(5px)}.confirm-body{padding:22px}.confirm-body h2{margin:0;color:#173f30;font-size:18px;font-weight:700}.confirm-body p{margin:10px 0 22px;color:#59685f;font-size:14px}.confirm-actions{display:flex;justify-content:flex-end;gap:9px}.confirm-actions button{min-height:38px;border:1px solid #d7e0da;border-radius:9px;background:#fff;padding:0 15px;color:#34463b;font-size:13px;font-weight:650}.confirm-actions [data-confirm-delete]{border-color:#b42318;background:#b42318;color:#fff}
    </style>
</head>
<body>
    <div class="list">
        @forelse($notifications as $notification)
            <article class="item {{ $notification->read_at ? '' : 'unread' }}">
                <button type="button" data-notification-trigger data-notification-detail="{{ json_encode($notification->data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}" data-notification-date="{{ $notification->created_at->format('d/m/Y H:i') }}" data-notification-read="{{ $notification->read_at ? '0' : '1' }}" data-notification-read-url="{{ route('notifications.read', $notification->id) }}" data-notification-delete-url="{{ route('notifications.destroy', $notification->id) }}" class="min-w-0 flex-1 cursor-pointer border-0 bg-transparent p-0 text-left">
                    <p class="message">{{ ($notification->data['message'] ?? null) === 'Nouvelle demande de compte' && !empty($notification->data['name']) ? 'Nouvelle demande de compte : '.$notification->data['name'] : ($notification->data['message'] ?? 'Notification NutriTrace') }}</p>
                    @if(!empty($notification->data['preview']))<p class="preview">{{ $notification->data['preview'] }}</p>@elseif(!empty($notification->data['email']))<p class="preview">{{ $notification->data['email'] }} · {{ match($notification->data['role'] ?? '') { 'producteur' => 'Producteur', 'transformateur' => 'Transformateur', 'distributeur' => 'Distributeur', default => 'Utilisateur' } }}</p>@endif
                    <time class="time">{{ $notification->created_at->diffForHumans() }}</time>
                </button>
                <div class="actions">
                    @if(!$notification->read_at)
                        <form method="post" action="{{ route('notifications.read', $notification->id) }}">@csrf<button type="submit">Marquer lue</button></form>
                    @endif
                    <form method="post" action="{{ route('notifications.destroy', $notification->id) }}" data-confirm-action="Supprimer cette notification ?">@csrf @method('DELETE')<button type="submit" class="delete" aria-label="Supprimer cette notification" title="Supprimer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m3 0-.8 14H5.8L5 6m4 4v6m6-6v6"/></svg></button></form>
                </div>
            </article>
        @empty
            <p class="rounded-xl bg-white p-6 text-center text-sm text-gray-500">Aucune notification pour le moment.</p>
        @endforelse
    </div>
    @if($notifications->hasPages())<div class="pagination">{{ $notifications->links() }}</div>@endif
    @include('profile.partials.notification-detail-dialog')
    <dialog id="notification-delete-dialog" aria-labelledby="notification-delete-title"><div class="confirm-body"><h2 id="notification-delete-title">Supprimer la notification ?</h2><p>Cette notification sera supprimée de votre compte.</p><div class="confirm-actions"><button type="button" data-cancel-delete>Annuler</button><button type="button" data-confirm-delete>Supprimer</button></div></div></dialog>
    <script>
        (() => {
            const dialog = document.getElementById('notification-delete-dialog');
            if (!dialog) return;
            let pendingForm = null;
            document.addEventListener('submit', event => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-action')) return;
                if (form.dataset.confirmed === 'true') { delete form.dataset.confirmed; return; }
                event.preventDefault(); pendingForm = form; dialog.showModal();
            });
            dialog.querySelector('[data-cancel-delete]').addEventListener('click', () => { pendingForm = null; dialog.close(); });
            dialog.querySelector('[data-confirm-delete]').addEventListener('click', () => { if (!pendingForm) return dialog.close(); const form = pendingForm; pendingForm = null; dialog.close(); form.dataset.confirmed = 'true'; form.requestSubmit(); });
            dialog.addEventListener('click', event => { if (event.target === dialog) { pendingForm = null; dialog.close(); } });
        })();
    </script>
</body>
</html>
