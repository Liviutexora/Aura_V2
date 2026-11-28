(function () {
    'use strict';

    var config = window.AURA_PWA || {};
    if (!config.enabled && !config.workerRetirement) return;

    var panel = document.querySelector('[data-pwa-install-panel]');
    var installButton = panel && panel.querySelector('[data-pwa-install]');
    var status = panel && panel.querySelector('[data-pwa-status]');
    var manual = panel && panel.querySelector('[data-pwa-manual]');
    var headerButton = document.querySelector('[data-pwa-header-install]');
    var headerStatus = document.querySelector('[data-pwa-header-status]');
    var deferredPrompt = null;
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    if (panel) panel.hidden = standalone;
    if (headerButton && headerStatus) {
        headerButton.hidden = standalone;
        headerStatus.hidden = !standalone;
    }

    if ('serviceWorker' in navigator && config.workerRetirement) {
        navigator.serviceWorker.getRegistration().then(function (registration) {
            if (registration) return registration.update();
        }).catch(function () {
            // The active site remains usable if a retired worker cannot be updated.
        });
    } else if (config.enabled && config.workerEnabled && 'serviceWorker' in navigator) {
        navigator.serviceWorker.register(config.workerUrl, {scope: '/'}).then(function (registration) {
            if (registration.waiting) showUpdate(registration.waiting);
            registration.addEventListener('updatefound', function () {
                var worker = registration.installing;
                if (!worker) return;
                worker.addEventListener('statechange', function () {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) showUpdate(worker);
                });
            });
        }).catch(function () {
            // PWA enhancement is optional; the regular website remains usable.
        });
    }

    if (!config.enabled) return;

    if (panel && window.location.search.includes('install=manual') && !standalone) showManual();

    window.addEventListener('beforeinstallprompt', function (event) {
        if (standalone) return;
        event.preventDefault();
        deferredPrompt = event;
        if (panel) panel.hidden = false;
        if (manual) manual.hidden = true;
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        if (panel) panel.hidden = true;
        if (headerButton && headerStatus) {
            headerButton.hidden = true;
            headerStatus.hidden = false;
        }
    });

    if (installButton) installButton.addEventListener('click', requestInstall);
    if (headerButton) headerButton.addEventListener('click', requestInstall);

    function requestInstall() {
        if (!deferredPrompt) {
            showManual();
            return;
        }

        var prompt = deferredPrompt;
        deferredPrompt = null;

        try {
            Promise.resolve(prompt.prompt()).then(function () {
                return prompt.userChoice;
            }).then(function (choice) {
                if (!choice || choice.outcome !== 'accepted') showManual();
            }).catch(showManual);
        } catch (error) {
            showManual();
        }
    }

    function showManual() {
        if (!panel) {
            window.location.assign(config.manualUrl);
            return;
        }
        panel.hidden = false;
        if (manual) {
            manual.hidden = false;
            manual.open = true;
        }
        if (status) status.textContent = 'Folosește instrucțiunile de mai jos pentru instalarea manuală.';
    }

    function showUpdate(worker) {
        var confirmed = window.confirm('Este disponibilă o versiune nouă AURA. O activezi acum? Se aplică la următoarea navigare sau redeschidere a aplicației.');
        if (!confirmed) return;
        worker.postMessage({type: 'SKIP_WAITING'});
    }
}());
