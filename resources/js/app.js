import './bootstrap';
import './profile-location';

import Alpine from 'alpinejs';

let standaloneAlpineStarted = false;

function pageUsesLivewire() {
    if (
        document.documentElement?.dataset?.livewirePage === 'true'
        || document.body?.dataset?.livewirePage === 'true'
        || document.querySelector('[data-livewire-page="true"]')
    ) {
        return true;
    }

    return Boolean(
        window.Livewire
        || window.livewire
        ||
        document.querySelector('script[data-update-uri], script[data-livewire-script]')
        || document.querySelector('script[src*="/livewire/livewire"], script[src*="livewire.js"], script[src*="livewire.min.js"]')
        || document.querySelector('[wire\\:id], [wire\\:model], [wire\\:click], [wire\\:submit], [wire\\:loading], [wire\\:target]')
    );
}

function startStandaloneAlpine() {
    if (standaloneAlpineStarted || window.Alpine) {
        return;
    }

    window.Alpine = Alpine;
    Alpine.start();
    standaloneAlpineStarted = true;
}

function bootAlpine() {
    const attemptStandaloneStart = () => {
        if (pageUsesLivewire()) {
            return;
        }

        startStandaloneAlpine();
    };

    // Run once on boot, then once more after footer scripts settle.
    attemptStandaloneStart();
    window.setTimeout(attemptStandaloneStart, 120);
    window.addEventListener('load', attemptStandaloneStart, { once: true });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAlpine, { once: true });
} else {
    bootAlpine();
}
