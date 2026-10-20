<!DOCTYPE html>
<html lang="fr" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="h-full bg-gray-50 dark:bg-gray-900">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Administration | NutriTrace')</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Theme Store -->
    <style>
        [x-cloak] {
            display: none !important;
        }
        #action-confirm-dialog{position:fixed;inset:0;margin:auto;width:min(550px,calc(100vw - 32px));max-width:calc(100vw - 32px);padding:0;border:1px solid #dce5df;border-radius:18px;background:#fff;color:#1f2937;box-shadow:0 24px 80px #10251b40}
        #action-confirm-dialog::backdrop{background:#101b16a8;backdrop-filter:blur(3px)}
        #action-confirm-dialog .confirm-content{padding:26px}
        #action-confirm-dialog .confirm-title{margin:0;color:#173f30;font-size:20px;font-weight:700}
        #action-confirm-dialog .confirm-message{margin:12px 0 24px;color:#59685f;font-size:14px;line-height:1.6}
        #action-confirm-dialog .confirm-actions{display:flex;justify-content:flex-end;gap:10px}
        #action-confirm-dialog .confirm-button{min-height:40px;padding:0 17px;border:1px solid #d7e0da;border-radius:9px;background:#fff;color:#34463b;font:inherit;font-size:14px;font-weight:650;cursor:pointer}
        #action-confirm-dialog .confirm-button-danger{border-color:#2d6a4f;background:#2d6a4f;color:#fff}
        #action-confirm-dialog .confirm-button:hover{filter:brightness(.96)}
        #detail-preview-dialog{position:fixed;inset:0;margin:auto;width:min(760px,calc(100vw - 32px));max-width:calc(100vw - 32px);max-height:min(82vh,850px);padding:0;overflow:auto;border:1px solid #dce5df;border-radius:20px;background:#fff;color:#1f2937;box-shadow:0 24px 80px #10251b40}
        #detail-preview-dialog::backdrop{background:#101b1680;backdrop-filter:blur(5px)}
        #detail-preview-dialog .detail-preview-shell{position:relative;padding:30px}
        #detail-preview-dialog .detail-preview-close{position:sticky;z-index:2;top:0;float:right;width:38px;height:38px;margin:-10px -10px 0 12px;border:1px solid #dce5df;border-radius:12px;background:#fff;color:#34463b;font-size:24px;line-height:1;cursor:pointer}
        #detail-preview-dialog .detail-preview-kicker{margin:0;color:#28724d;font-size:12px;font-weight:750;letter-spacing:.12em;text-transform:uppercase}
        #detail-preview-dialog .detail-preview-title{margin:8px 0 0;color:#173f30;font-size:28px;font-weight:750;line-height:1.2}
        #detail-preview-dialog .detail-preview-meta{margin:8px 0 0;color:#718078;font-size:13px}
        #detail-preview-dialog .detail-preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:24px 0 0}
        #detail-preview-dialog .detail-preview-item{min-width:0;padding:14px 16px;border-radius:12px;background:#f5f8f6}
        #detail-preview-dialog .detail-preview-item dt{color:#718078;font-size:12px}
        #detail-preview-dialog .detail-preview-item dd{margin:5px 0 0;overflow-wrap:anywhere;color:#24372e;font-size:14px;font-weight:650;white-space:pre-line}
        #detail-preview-dialog .detail-preview-related{display:grid;gap:12px;margin-top:18px}
        #detail-preview-dialog .detail-preview-related section{padding:16px;border:1px solid #e1e9e4;border-radius:12px}
        #detail-preview-dialog .detail-preview-related h3{margin:0 0 8px;color:#173f30;font-size:14px;font-weight:700}
        #detail-preview-dialog .detail-preview-related ul{margin:0;padding:0;list-style:none;color:#43534a;font-size:13px;line-height:1.8}
        #detail-preview-dialog .detail-preview-muted{color:#85918a}
        #detail-preview-dialog .detail-preview-footer{display:flex;justify-content:flex-end;margin-top:24px;padding-top:18px;border-top:1px solid #e7ede9}
        #detail-preview-dialog .detail-preview-footer a{display:inline-flex;align-items:center;min-height:42px;padding:0 18px;border-radius:10px;background:#2d6a4f;color:#fff;font-size:14px;font-weight:700;text-decoration:none}
        #detail-preview-dialog .detail-preview-footer a:hover{background:#24563f}
        @media(max-width:600px){#detail-preview-dialog .detail-preview-shell{padding:22px}#detail-preview-dialog .detail-preview-grid{grid-template-columns:1fr}#detail-preview-dialog .detail-preview-title{font-size:23px}}
    </style>
    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    this.theme = savedTheme === 'dark' ? 'dark' : 'light';
                    this.updateTheme();
                },
                theme: 'light',
                resolvedTheme: 'light',
                set(value) {
                    value = value === 'dark' ? 'dark' : 'light';
                    this.theme = value;
                    localStorage.setItem('theme', value);
                    this.updateTheme();
                    window.dispatchEvent(new CustomEvent('theme-changed', { detail: value }));
                },
                toggle() {
                    this.set(this.resolvedTheme === 'dark' ? 'light' : 'dark');
                },
                updateTheme() {
                    const html = document.documentElement;
                    const isDark = this.theme === 'dark';
                    if (isDark) {
                        html.classList.add('dark');
                    } else {
                        html.classList.remove('dark');
                    }

                    this.resolvedTheme = isDark ? 'dark' : 'light';
                    html.setAttribute('data-color-scheme', this.resolvedTheme);
                    html.dataset['theme'] = this.resolvedTheme;
                    html.style.colorScheme = this.resolvedTheme;
                    if (document.body) {
                        document.body.dataset['theme'] = this.resolvedTheme;
                        document.body.style.colorScheme = this.resolvedTheme;
                    }
                }
            });

            Alpine.store('sidebar', {
                isExpanded: false,
                isMobileOpen: false,
                isHovered: false,

                init() {
                    const savedState = localStorage.getItem('sidebarExpanded');
                    if (window.innerWidth >= 1280) {
                        this.isExpanded = savedState === null ? true : savedState === 'true';
                    } else {
                        this.isExpanded = false;
                    }
                    this.isMobileOpen = false;

                    window.addEventListener('resize', () => {
                        this.handleResize();
                    });
                },

                handleResize() {
                    if (window.innerWidth < 1280) {
                        if (this.isMobileOpen) {
                             this.isMobileOpen = false;
                        }
                    } else {
                        this.isMobileOpen = false;
                        const savedState = localStorage.getItem('sidebarExpanded');
                        this.isExpanded = savedState === null ? true : savedState === 'true';
                    }
                },

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    this.isMobileOpen = false;
                    
                    if (window.innerWidth >= 1280) {
                        localStorage.setItem('sidebarExpanded', this.isExpanded);
                    }
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });
        });
    </script>

    <!-- Apply RTL and dark mode immediately to prevent flash -->
    <script>
        (function() {
            const savedDir = localStorage.getItem('dir');
            const savedLocale = localStorage.getItem('locale');
            if (savedDir) {
                document.documentElement.setAttribute('dir', savedDir);
            } else if (savedLocale === 'ar') {
                document.documentElement.setAttribute('dir', 'rtl');
            }
            if (savedLocale) {
                document.documentElement.setAttribute('lang', savedLocale);
            }

            const savedTheme = localStorage.getItem('theme');
            const isDark = savedTheme === 'dark';
            if (isDark) {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-color-scheme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-color-scheme', 'light');
            }
        })();
    </script>
    

</head>

<body>

    <div class="min-h-screen xl:flex sidebar-expanded" x-data :class="{ 'sidebar-expanded': $store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen }">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        {{-- transition-all duration-300 ease-in-out --}}
        <div class="flex-1 ml-0 ltr:xl:ml-[90px] rtl:xl:ml-0 rtl:xl:mr-[90px] [.sidebar-expanded_&]:ltr:xl:ml-[290px] [.sidebar-expanded_&]:rtl:xl:ml-0 [.sidebar-expanded_&]:rtl:xl:mr-[290px] transition-all duration-300 ease-in-out">
            <!-- app header start -->
            @include('layouts.app-header')
            <!-- app header end -->
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                @yield('content')
            </div>
        </div>

    </div>

    <dialog id="action-confirm-dialog" aria-labelledby="action-confirm-title" aria-describedby="action-confirm-message">
        <div class="confirm-content">
            <h2 id="action-confirm-title" class="confirm-title">Confirmer l’action</h2>
            <p id="action-confirm-message" class="confirm-message">Voulez-vous continuer ?</p>
            <div class="confirm-actions">
                <button type="button" class="confirm-button" data-confirm-cancel>Annuler</button>
                <button type="button" class="confirm-button confirm-button-danger" data-confirm-submit>Confirmer</button>
            </div>
        </div>
    </dialog>
    <dialog id="detail-preview-dialog" aria-label="Détails de l’enregistrement">
        <div class="detail-preview-shell">
            <button type="button" class="detail-preview-close" aria-label="Fermer" data-detail-preview-close>&times;</button>
            <div id="detail-preview-content" aria-live="polite"></div>
        </div>
    </dialog>
    <script>
        (() => {
            const dialog = document.getElementById('action-confirm-dialog');
            if (!dialog) return;

            let pendingForm = null;
            const message = dialog.querySelector('#action-confirm-message');
            const confirmButton = dialog.querySelector('[data-confirm-submit]');

            document.addEventListener('submit', (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-action')) return;
                if (form.dataset.confirmed === 'true') {
                    delete form.dataset.confirmed;
                    return;
                }

                event.preventDefault();
                pendingForm = form;
                message.textContent = form.dataset.confirmAction || 'Voulez-vous continuer ?';
                confirmButton.textContent = /supprimer/i.test(message.textContent) ? 'Supprimer' : (/révoquer/i.test(message.textContent) ? 'Révoquer' : (/désactiver/i.test(message.textContent) ? 'Désactiver' : 'Confirmer'));
                dialog.showModal();
            });

            dialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => {
                pendingForm = null;
                dialog.close();
            });

            confirmButton.addEventListener('click', () => {
                if (!pendingForm) return dialog.close();
                const form = pendingForm;
                pendingForm = null;
                dialog.close();
                form.dataset.confirmed = 'true';
                form.requestSubmit();
            });

            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    pendingForm = null;
                    dialog.close();
                }
            });
            dialog.addEventListener('close', () => { pendingForm = null; });
        })();
    </script>
    <script>
        (() => {
            const dialog = document.getElementById('detail-preview-dialog');
            const content = document.getElementById('detail-preview-content');
            if (!dialog || !content) return;

            document.addEventListener('click', async (event) => {
                const link = event.target.closest('a[data-detail-popup]');
                if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

                event.preventDefault();
                const target = new URL(link.href, window.location.href);
                target.searchParams.set('popup', '1');
                content.innerHTML = '<p style="padding:24px;color:#718078">Chargement des détails…</p>';
                dialog.showModal();

                try {
                    const response = await fetch(target, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) throw new Error('Preview indisponible');
                    content.innerHTML = await response.text();
                } catch (error) {
                    dialog.close();
                    window.location.assign(link.href);
                }
            });

            dialog.querySelector('[data-detail-preview-close]').addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
            dialog.addEventListener('close', () => { content.replaceChildren(); });
        })();
    </script>

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

@stack('scripts')

</html>
