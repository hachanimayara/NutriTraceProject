import { createPopper } from '@popperjs/core';
import './bootstrap';
import Alpine from 'alpinejs';

// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
window.Alpine = Alpine;
window.createPopper = createPopper;
window.flatpickr = flatpickr;

Alpine.start();

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    const addPasskey = document.querySelector('#add-passkey');
    const faceContent = addPasskey?.closest('.profile-face-content');
    let passkeyStatus = faceContent?.querySelector('#passkey-status');
    const passkeyConsent = faceContent?.querySelector('#passkey-consent');
    const passkeyName = faceContent?.querySelector('#passkey-name');
    if (faceContent && !passkeyStatus) {
        passkeyStatus = document.createElement('p');
        passkeyStatus.id = 'passkey-status';
        passkeyStatus.setAttribute('role', 'status');
        passkeyStatus.setAttribute('aria-live', 'polite');
        passkeyStatus.hidden = true;
        addPasskey.insertAdjacentElement('afterend', passkeyStatus);
    }
    addPasskey?.addEventListener('click', async () => {
        if (passkeyConsent && !passkeyConsent.checked) {
            passkeyStatus.hidden = false;
            passkeyStatus.className = 'profile-message error';
            passkeyStatus.textContent = 'Veuillez lire et accepter le fonctionnement de la clé d’accès avant de continuer.';
            passkeyConsent.focus();
            return;
        }

        const initialLabel = addPasskey.textContent.trim();
        addPasskey.disabled = true;
        addPasskey.textContent = 'En attente de votre appareil…';
        if (passkeyStatus) {
            passkeyStatus.hidden = false;
            passkeyStatus.className = 'profile-message success';
            passkeyStatus.textContent = 'Votre navigateur va ouvrir sa fenêtre sécurisée pour configurer Face ID, Windows Hello ou une autre méthode biométrique.';
        }

        try {
            const consentResponse = await fetch(addPasskey.dataset.consentUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ consent: true }),
            });
            if (!consentResponse.ok) {
                throw new Error('Le consentement n’a pas pu être enregistré. Rechargez la page et réessayez.');
            }

            const { Passkeys } = await import('@laravel/passkeys');
            await Passkeys.register({ name: passkeyName?.value.trim() || 'Mon appareil' });
            sessionStorage.setItem('nutritrace.passkey-registration-success', '1');
            window.location.reload();
        } catch (error) {
            if (passkeyStatus) {
                passkeyStatus.className = 'profile-message error';
                passkeyStatus.textContent = error?.name === 'NotAllowedError'
                    ? 'La configuration a été annulée ou a expiré. Cliquez à nouveau pour réessayer.'
                    : error?.name === 'SecurityError'
                        ? 'Cette adresse ne permet pas la biométrie sécurisée. Utilisez 127.0.0.1 ou HTTPS.'
                        : (error?.message || 'La création de la clé d’accès a échoué. Réessayez.');
            }
            addPasskey.disabled = false;
            addPasskey.textContent = initialLabel;
        }
    });
    const passkeyLogin = document.querySelector('#passkey-login');
    const passkeyLoginStatus = document.querySelector('#passkey-login-status');
    passkeyLogin?.addEventListener('click', async () => {
        passkeyLogin.disabled = true;
        passkeyLogin.textContent = 'En attente de votre appareil…';
        if (passkeyLoginStatus) {
            passkeyLoginStatus.hidden = false;
            passkeyLoginStatus.className = 'alert';
            passkeyLoginStatus.textContent = 'Confirmez votre identité dans la fenêtre sécurisée de votre appareil.';
        }

        try {
            const { Passkeys } = await import('@laravel/passkeys');
            await Passkeys.verify();
            window.location.assign('/dashboard');
        } catch (error) {
            if (passkeyLoginStatus) {
                passkeyLoginStatus.className = 'alert warn';
                passkeyLoginStatus.textContent = error?.name === 'NotAllowedError'
                    ? 'La vérification a été annulée ou aucune clé compatible n’a été trouvée. Connectez-vous avec votre e-mail et votre mot de passe.'
                    : (error?.message || 'La vérification a échoué. Connectez-vous avec votre e-mail et votre mot de passe.');
            }
            passkeyLogin.disabled = false;
            passkeyLogin.textContent = 'Connexion biométrique (Face ID / appareil)';
        }
    });
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-1')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartOne();
        });
    }
    if (document.querySelector('#chartTwo')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-2')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartTwo();
        });
    }
    if (document.querySelector('#chartThree')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-3')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartThree();
        });
    }
    if (document.querySelector('#chartSix')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-6')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartSix();
        });
    }
    if (document.querySelector('#chartEight')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-8')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartEight();
        });
    }
    if (document.querySelector('#chartThirteen')) {
        Promise.all([import('apexcharts'), import('./components/chart/chart-13')]).then(([chart, module]) => {
            window.ApexCharts = chart.default;
            module.initChartThirteen();
        });
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }
});
