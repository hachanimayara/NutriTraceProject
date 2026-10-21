import '../../css/messaging.css';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);
const initials = (name) => String(name || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0] || '').join('').toUpperCase();
const roleLabel = (role) => role === 'transformateur' ? 'Transformateur' : role === 'distributeur' ? 'Distributeur' : role;
const formatDate = (value, options = { dateStyle: 'short', timeStyle: 'short' }) => {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('fr-FR', options).format(date);
};
const relativeDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    const elapsed = Date.now() - date.getTime();
    if (!Number.isFinite(elapsed) || elapsed < 0) return '';
    if (elapsed < 60_000) return 'à l’instant';
    if (elapsed < 3_600_000) return `il y a ${Math.floor(elapsed / 60_000)} min`;
    if (elapsed < 86_400_000) return `il y a ${Math.floor(elapsed / 3_600_000)} h`;
    return formatDate(value, { day: '2-digit', month: '2-digit' });
};
const makeUuid = () => window.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });

function avatarMarkup(person, sizeClass = '') {
    const image = person?.avatar_url ? `<img src="${escapeHtml(person.avatar_url)}" alt="">` : escapeHtml(initials(person?.name));
    return `<span class="messaging-avatar ${sizeClass}" data-online="${person?.online ? 'true' : 'false'}">${image}<i aria-hidden="true"></i></span>`;
}

export function initMessaging() {
    const root = document.querySelector('[data-messaging]');
    if (!root) return;
    const shell = root.querySelector('.messaging-shell');
    const list = root.querySelector('[data-conversation-list]');
    const search = root.querySelector('[data-search]');
    const contactsPanel = root.querySelector('[data-contact-results]');
    const messagesPanel = root.querySelector('[data-message-results]');
    const directory = root.querySelector('[data-directory]');
    const directoryList = root.querySelector('[data-directory-list]');
    const thread = root.querySelector('[data-thread]');
    const placeholder = root.querySelector('[data-thread-placeholder]');
    const messageList = root.querySelector('[data-message-list]');
    const composer = root.querySelector('[data-composer]');
    const textarea = composer.querySelector('textarea');
    const sendButton = composer.querySelector('button');
    const threadHeader = root.querySelector('[data-thread-header]');
    const threadError = root.querySelector('[data-thread-error]');
    const olderButton = root.querySelector('[data-load-older]');
    const connection = root.querySelector('[data-connection-status]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const currentUser = Number(root.dataset.currentUser);
    let conversations = [];
    let active = null;
    let activeGeneration = 0;
    let lastMessageId = 0;
    let hasMore = false;
    let inboxTimer = null;
    let messageTimer = null;
    let presenceTimer = null;
    let searchTimer = null;
    let pendingBody = null;
    let pendingUuid = null;
    let stateBusy = false;
    let messagesBusy = false;

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.method && options.method !== 'GET' ? { 'X-CSRF-TOKEN': csrf } : {}), ...(options.headers || {}) },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(data.message || 'Une erreur est survenue. Réessayez.');
            error.status = response.status;
            throw error;
        }
        return data;
    };
    const setConnection = (online, text) => {
        connection.dataset.state = online ? 'online' : 'offline';
        connection.innerHTML = `<span aria-hidden="true"></span>${escapeHtml(text)}`;
    };
    const updateUnreadBadges = (count) => {
        const value = Math.max(0, Number(count) || 0);
        root.querySelector('[data-inbox-count]').textContent = String(value);
        document.querySelectorAll('[data-messaging-unread-badge]').forEach((badge) => {
            badge.textContent = value > 99 ? '99+' : String(value);
            badge.hidden = value === 0;
        });
    };
    const syncLatestStatus = (conversation) => {
        const last = conversation?.last_message;
        if (!last || Number(last.sender_id) !== currentUser || !last.status) return;
        const status = messageList.querySelector(`[data-message-id="${Number(last.id)}"] .messaging-message-status`);
        if (!status) return;
        status.dataset.status = last.status;
        status.title = ({ read: 'Lu', delivered: 'Livré', sent: 'Envoyé' })[last.status] || 'Envoyé';
        status.textContent = last.status === 'sent' ? '✓' : '✓✓';
    };
    const createToast = (message) => {
        const toast = document.createElement('button');
        toast.type = 'button';
        toast.className = 'messaging-toast';
        const title = document.createElement('strong');
        title.textContent = `Nouveau message · ${message.sender_name}`;
        const preview = document.createElement('span');
        preview.textContent = message.body;
        toast.append(title, preview);
        toast.addEventListener('click', () => openById(message.conversation_id));
        root.querySelector('[data-toasts]').append(toast);
        window.setTimeout(() => toast.remove(), 8000);
    };
    const renderConversations = () => {
        root.querySelector('[data-inbox-count]').textContent = String(conversations.reduce((sum, item) => sum + item.unread_count, 0));
        if (!conversations.length) {
            list.innerHTML = '<p class="messaging-empty">Aucune conversation pour le moment. Recherchez un transformateur ou un distributeur pour commencer.</p>';
            return;
        }
        list.innerHTML = conversations.map((conversation) => {
            const person = conversation.participant;
            const preview = conversation.last_message ? `${conversation.last_message.sender_id === currentUser ? 'Vous : ' : ''}${conversation.last_message.body}` : 'Conversation créée — envoyez le premier message.';
            return `<button type="button" class="messaging-conversation" data-conversation-id="${conversation.id}" data-active="${active?.id === conversation.id}" aria-label="Conversation avec ${escapeHtml(person.name)}">${avatarMarkup(person)}<span class="messaging-conversation-copy"><span class="messaging-conversation-top"><strong class="messaging-conversation-name">${escapeHtml(person.name)}</strong><time class="messaging-conversation-time">${escapeHtml(relativeDate(conversation.last_message_at))}</time></span><span class="messaging-conversation-preview"><span>${escapeHtml(preview)}</span><b class="messaging-unread" ${conversation.unread_count ? '' : 'hidden'}>${conversation.unread_count > 99 ? '99+' : conversation.unread_count}</b></span></span></button>`;
        }).join('');
    };
    const renderContacts = (contacts, term) => {
        if (!term || term.length < 2) { contactsPanel.hidden = true; contactsPanel.innerHTML = ''; return; }
        contactsPanel.hidden = false;
        contactsPanel.innerHTML = `<p class="messaging-search-section">Interlocuteurs</p>${contacts.length ? contacts.map((person) => `<button type="button" class="messaging-search-result" data-contact-id="${person.id}">${avatarMarkup(person)}<span><strong>${escapeHtml(person.name)}</strong> · ${escapeHtml(roleLabel(person.role))}${person.organization ? ` · ${escapeHtml(person.organization)}` : ''}</span></button>`).join('') : '<p class="messaging-empty">Aucun interlocuteur trouvé.</p>'}`;
    };
    const renderDirectory = (contacts, term) => {
        directory.hidden = Boolean(term && term.length >= 2);
        if (directory.hidden) return;
        if (!contacts.length) {
            directoryList.innerHTML = '<p class="messaging-empty">Aucun interlocuteur disponible.</p>';
            return;
        }
        directoryList.innerHTML = contacts.map((person) => `<button type="button" class="messaging-directory-contact" data-contact-id="${person.id}">${avatarMarkup(person)}<span class="messaging-directory-copy"><strong>${escapeHtml(person.name)}</strong><small>${escapeHtml(person.organization || roleLabel(person.role))}</small></span><span class="messaging-directory-status" data-online="${person.online ? 'true' : 'false'}">${person.online ? 'En ligne' : 'Contacter'}</span></button>`).join('');
    };
    const renderMessageResults = (results, term) => {
        if (!term || term.length < 2) { messagesPanel.hidden = true; messagesPanel.innerHTML = ''; return; }
        messagesPanel.hidden = false;
        messagesPanel.innerHTML = `<p class="messaging-search-section">Messages trouvés</p>${results.length ? results.map((item) => `<button type="button" class="messaging-search-result" data-result-conversation="${item.conversation_id}"><span class="messaging-search-result-copy"><strong>${escapeHtml(item.participant_name || 'Conversation')}</strong><small>${escapeHtml(item.sender_name)} · ${escapeHtml(formatDate(item.created_at))}</small><span>${escapeHtml(item.body)}</span></span></button>`).join('') : '<p class="messaging-empty">Aucun message correspondant.</p>'}`;
    };
    const renderState = (data, term) => {
        conversations = data.conversations || [];
        renderConversations();
        renderDirectory(data.directory_contacts || [], term);
        renderContacts(data.contacts || [], term);
        renderMessageResults(data.message_results || [], term);
        updateUnreadBadges(data.unread_count);
        if (active) {
            const updated = conversations.find((item) => item.id === active.id);
            if (updated?.participant) {
                active = updated;
                renderThreadHeader(updated.participant);
                renderConversations();
                syncLatestStatus(updated);
            }
        }
    };
    const refreshState = async () => {
        if (stateBusy) return;
        stateBusy = true;
        const term = search.value.trim();
        try {
            const url = new URL(root.dataset.stateUrl, window.location.origin);
            if (term) url.searchParams.set('q', term);
            const data = await request(url);
            renderState(data, term);
            setConnection(true, 'Synchronisation active');
            const initialId = Number(root.dataset.openConversation);
            if (!active && initialId) {
                const initial = conversations.find((item) => item.id === initialId);
                if (initial) openConversation(initial);
            }
        } catch (error) {
            setConnection(false, 'Reconnexion en cours');
            if (error.status === 401 || error.status === 419) window.location.assign('/login');
        } finally {
            stateBusy = false;
        }
    };
    const renderThreadHeader = (person) => {
        const lastSeen = person.online ? 'En ligne' : person.last_active_at ? `Hors ligne · dernière activité ${relativeDate(person.last_active_at)}` : 'Hors ligne';
        threadHeader.innerHTML = `<button type="button" class="messaging-back" data-back-to-inbox aria-label="Retour aux conversations">←</button>${avatarMarkup(person)}<div class="messaging-thread-person"><strong>${escapeHtml(person.name)}</strong><span>${escapeHtml(roleLabel(person.role))} · ${escapeHtml(lastSeen)}</span></div>`;
    };
    const renderMessage = (message) => {
        const own = Number(message.sender_id) === currentUser;
        const row = document.createElement('article');
        row.className = 'messaging-message-row';
        row.dataset.messageId = String(message.id);
        row.dataset.own = own ? 'true' : 'false';
        if (!own) {
            const sender = document.createElement('span');
            sender.className = 'messaging-message-sender';
            sender.textContent = message.sender_name;
            row.append(sender);
        }
        const bubble = document.createElement('div');
        bubble.className = 'messaging-bubble';
        bubble.textContent = message.body;
        const meta = document.createElement('div');
        meta.className = 'messaging-message-meta';
        const time = document.createElement('time');
        time.dateTime = message.created_at || '';
        time.textContent = formatDate(message.created_at);
        meta.append(time);
        if (own) {
            const status = document.createElement('span');
            status.className = 'messaging-message-status';
            status.dataset.status = message.status || 'sent';
            status.title = ({ read: 'Lu', delivered: 'Livré', sent: 'Envoyé' })[message.status] || 'Envoyé';
            status.textContent = message.status === 'read' ? '✓✓' : message.status === 'delivered' ? '✓✓' : '✓';
            meta.append(status);
        }
        row.append(bubble, meta);
        return row;
    };
    const appendMessages = (items, { prepend = false, notify = false } = {}) => {
        const known = new Set([...messageList.querySelectorAll('[data-message-id]')].map((item) => item.dataset.messageId));
        const fresh = items.filter((item) => !known.has(String(item.id)));
        const shouldFollow = messageList.scrollHeight - messageList.scrollTop - messageList.clientHeight < 90;
        if (prepend) {
            const oldHeight = messageList.scrollHeight;
            const fragment = document.createDocumentFragment();
            fresh.forEach((item) => fragment.append(renderMessage(item)));
            messageList.prepend(fragment);
            messageList.scrollTop += messageList.scrollHeight - oldHeight;
        } else {
            const fragment = document.createDocumentFragment();
            fresh.forEach((item) => fragment.append(renderMessage(item)));
            messageList.append(fragment);
            if (shouldFollow || !known.size) messageList.scrollTop = messageList.scrollHeight;
        }
        if (fresh.length) {
            lastMessageId = Math.max(lastMessageId, ...fresh.map((item) => Number(item.id) || 0));
            if (notify) fresh.filter((item) => Number(item.sender_id) !== currentUser).forEach(createToast);
        }
    };
    const messageUrl = (conversationId) => root.dataset.messagesUrl.replace('__CONVERSATION__', encodeURIComponent(conversationId));
    const setComposerEnabled = (enabled) => {
        textarea.disabled = !enabled;
        sendButton.disabled = !enabled || !textarea.value.trim();
    };
    async function openConversation(conversation) {
        if (!conversation?.participant) return;
        active = conversation;
        activeGeneration++;
        const generation = activeGeneration;
        lastMessageId = 0;
        hasMore = false;
        pendingBody = null;
        pendingUuid = null;
        placeholder.hidden = true;
        thread.hidden = false;
        shell.dataset.mobileView = 'thread';
        messageList.replaceChildren();
        threadError.hidden = true;
        olderButton.hidden = true;
        setComposerEnabled(true);
        renderThreadHeader(conversation.participant);
        renderConversations();
        const url = new URL(messageUrl(conversation.id), window.location.origin);
        try {
            const data = await request(url);
            if (generation !== activeGeneration) return;
            appendMessages(data.messages || []);
            hasMore = Boolean(data.has_more);
            olderButton.hidden = !hasMore;
            lastMessageId = Number(data.last_id) || lastMessageId;
            if (!data.messages?.length) messageList.innerHTML = '<p class="messaging-empty">Aucun message pour le moment. Envoyez le premier message.</p>';
            else messageList.querySelector('.messaging-empty')?.remove();
            refreshState();
        } catch (error) {
            if (generation !== activeGeneration) return;
            showThreadError(error.message);
        }
        if (window.history?.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.set('conversation', conversation.id);
            window.history.replaceState({}, '', url);
        }
        if (window.matchMedia('(max-width: 760px)').matches) root.querySelector('[data-message-list]').focus({ preventScroll: true });
    }
    function showThreadError(message) {
        threadError.textContent = message;
        threadError.hidden = false;
    }
    async function openById(conversationId) {
        let conversation = conversations.find((item) => item.id === Number(conversationId));
        if (!conversation) {
            await refreshState();
            conversation = conversations.find((item) => item.id === Number(conversationId));
        }
        if (conversation) openConversation(conversation);
    }
    const pollMessages = async () => {
        if (!active || messagesBusy) return;
        messagesBusy = true;
        const generation = activeGeneration;
        try {
            const url = new URL(messageUrl(active.id), window.location.origin);
            if (lastMessageId) url.searchParams.set('after_id', String(lastMessageId));
            const data = await request(url);
            if (generation !== activeGeneration) return;
            appendMessages(data.messages || [], { notify: true });
            messageList.querySelector('.messaging-empty')?.remove();
            threadError.hidden = true;
            setConnection(true, 'Synchronisation active');
            if (lastMessageId && active.last_message?.id === lastMessageId) {
                active.last_message.status = data.messages?.some((message) => Number(message.sender_id) !== currentUser) ? 'read' : active.last_message.status;
            }
        } catch (error) {
            setConnection(false, 'Reconnexion en cours');
            if (error.status === 401 || error.status === 419) window.location.assign('/login');
        } finally {
            messagesBusy = false;
        }
    };
    const openContact = async (userId) => {
        try {
            const data = await request(root.dataset.createUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: userId }) });
            search.value = '';
            await refreshState();
            await openById(data.conversation_id);
        } catch (error) {
            showThreadError(error.message);
        }
    };

    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = window.setTimeout(refreshState, 350);
    });
    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-conversation-id]');
        if (button) openById(button.dataset.conversationId);
    });
    contactsPanel.addEventListener('click', (event) => {
        const button = event.target.closest('[data-contact-id]');
        if (button) openContact(button.dataset.contactId);
    });
    directoryList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-contact-id]');
        if (button) openContact(button.dataset.contactId);
    });
    messagesPanel.addEventListener('click', (event) => {
        const button = event.target.closest('[data-result-conversation]');
        if (button) openById(button.dataset.resultConversation);
    });
    threadHeader.addEventListener('click', (event) => {
        if (event.target.closest('[data-back-to-inbox]')) shell.dataset.mobileView = 'inbox';
    });
    textarea.addEventListener('input', () => {
        if (pendingBody !== textarea.value) { pendingBody = null; pendingUuid = null; }
        sendButton.disabled = !active || !textarea.value.trim();
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 140)}px`;
    });
    textarea.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            composer.requestSubmit();
        }
    });
    composer.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!active || !textarea.value.trim() || sendButton.disabled) return;
        const body = textarea.value.trim();
        if (pendingBody !== body || !pendingUuid) { pendingBody = body; pendingUuid = makeUuid(); }
        sendButton.disabled = true;
        threadError.hidden = true;
        try {
            const data = await request(root.dataset.sendUrl.replace('__CONVERSATION__', encodeURIComponent(active.id)), {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ body, client_uuid: pendingUuid }),
            });
            messageList.querySelector('.messaging-empty')?.remove();
            appendMessages([data.message]);
            textarea.value = '';
            textarea.style.height = 'auto';
            pendingBody = null;
            pendingUuid = null;
            setComposerEnabled(true);
            refreshState();
        } catch (error) {
            showThreadError(`${error.message} Votre texte est conservé pour réessayer.`);
            sendButton.disabled = false;
        }
    });
    olderButton.addEventListener('click', async () => {
        const oldest = messageList.querySelector('[data-message-id]')?.dataset.messageId;
        if (!active || !oldest || !hasMore) return;
        olderButton.disabled = true;
        const url = new URL(messageUrl(active.id), window.location.origin);
        url.searchParams.set('before_id', oldest);
        try {
            const data = await request(url);
            appendMessages(data.messages || [], { prepend: true });
            hasMore = Boolean(data.has_more);
            olderButton.hidden = !hasMore;
        } catch (error) {
            showThreadError(error.message);
        } finally {
            olderButton.disabled = false;
        }
    });

    const heartbeat = () => request(root.dataset.presenceUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' }).catch(() => {});
    refreshState();
    heartbeat();
    inboxTimer = window.setInterval(refreshState, 10_000);
    messageTimer = window.setInterval(pollMessages, 3_000);
    presenceTimer = window.setInterval(heartbeat, 30_000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { heartbeat(); refreshState(); pollMessages(); } });
    window.addEventListener('beforeunload', () => { clearInterval(inboxTimer); clearInterval(messageTimer); clearInterval(presenceTimer); clearTimeout(searchTimer); });
}
