<style>
    #notification-detail-dialog{position:fixed;inset:0;margin:auto;width:min(560px,calc(100vw - 28px));max-width:none;max-height:min(80dvh,680px);padding:0;overflow:hidden;border:1px solid #dce5df;border-radius:18px;background:#fff;color:#26352d;box-shadow:0 24px 80px #10251b40}
    #notification-detail-dialog::backdrop{background:rgba(16,35,29,.48);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px)}
    #notification-detail-dialog .notification-detail-body{max-height:80dvh;overflow-y:auto;padding:24px}
</style>
<dialog id="notification-detail-dialog" aria-labelledby="notification-detail-title">
    <div class="notification-detail-body">
        <div class="flex items-start justify-between gap-4">
            <div><p class="text-xs font-semibold uppercase tracking-[.14em] text-green-800">Détail de la notification</p><h2 id="notification-detail-title" data-notification-title class="mt-2 text-xl font-semibold text-gray-900">Notification</h2></div>
            <button type="button" data-close-notification-detail class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-xl text-gray-600 hover:bg-gray-50" aria-label="Fermer">×</button>
        </div>
        <time data-notification-date class="mt-2 block text-sm text-gray-500"></time>
        <dl data-notification-fields class="mt-5 grid gap-3"></dl>
        <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-gray-100 pt-5">
            <form method="post" data-notification-read-form>@csrf<button type="submit" class="rounded-xl border border-green-800 px-4 py-2.5 text-sm font-semibold text-green-800 hover:bg-green-50">Marquer comme lue</button></form>
            <form method="post" data-notification-delete-form data-confirm-action="Supprimer cette notification ?">@csrf @method('DELETE')<button type="submit" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">Supprimer</button></form>
        </div>
    </div>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('notification-detail-dialog');
        if (!dialog || dialog.dataset.bound === '1') return;
        dialog.dataset.bound = '1';
        const title = dialog.querySelector('[data-notification-title]');
        const date = dialog.querySelector('[data-notification-date]');
        const fields = dialog.querySelector('[data-notification-fields]');
        const readForm = dialog.querySelector('[data-notification-read-form]');
        const deleteForm = dialog.querySelector('[data-notification-delete-form]');
        const labels = { preview: 'Détail', severity: 'Priorité', due_at: 'Échéance', sender_name: 'Expéditeur', email: 'Adresse e-mail', role: 'Type de compte', name: 'Nom', reference: 'Référence', product: 'Produit', farm: 'Exploitation', estimated_quantity: 'Quantité prévue', unit: 'Unité' };
        const hiddenKeys = new Set(['type', 'url', 'alert_id', 'user_id', 'sender_id', 'conversation_id', 'chat_message_id', 'harvest_plan_id', 'event_key', 'message']);

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-notification-trigger]');
            if (!trigger || event.target.closest('form, a, button:not([data-notification-trigger])')) return;

            let payload = {};
            try { payload = JSON.parse(trigger.dataset.notificationDetail || '{}'); } catch (_) {}
            title.textContent = payload.message || 'Notification NutriTrace';
            date.textContent = trigger.dataset.notificationDate || '';
            fields.replaceChildren();

            Object.entries(payload).forEach(([key, value]) => {
                if (hiddenKeys.has(key) || value === null || value === '' || typeof value === 'object') return;
                const row = document.createElement('div');
                row.className = 'rounded-xl bg-gray-50 px-4 py-3';
                const label = document.createElement('dt');
                label.className = 'text-xs text-gray-500';
                label.textContent = labels[key] || key.replaceAll('_', ' ');
                const content = document.createElement('dd');
                content.className = 'mt-1 break-words text-sm font-medium text-gray-800';
                const displayValue = key === 'role' ? ({ producteur: 'Producteur', transformateur: 'Transformateur', distributeur: 'Distributeur', admin: 'Administrateur' }[value] || value) : value;
                content.textContent = key.endsWith('_at') && !Number.isNaN(Date.parse(value)) ? new Date(value).toLocaleString('fr-FR') : displayValue;
                row.append(label, content);
                fields.append(row);
            });

            readForm.action = trigger.dataset.notificationReadUrl || '#';
            readForm.hidden = trigger.dataset.notificationRead !== '1';
            deleteForm.action = trigger.dataset.notificationDeleteUrl || '#';
            dialog.showModal();
        });

        document.addEventListener('keydown', (event) => {
            const trigger = event.target.closest?.('[data-notification-trigger][role="button"]');
            if (!trigger || !['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            trigger.click();
        });

        dialog.querySelector('[data-close-notification-detail]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    })();
</script>
