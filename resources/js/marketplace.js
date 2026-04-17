import './marketplace-home-state';
import './profile-location';

function initAdminBackLogoutGuard() {
    const logoutForm = document.querySelector('#back-logout-form[data-back-logout-guard="true"]');
    if (!logoutForm || !window.history?.pushState) {
        return;
    }

    window.history.pushState({ guard: 'admin-back-logout' }, '', window.location.href);

    window.addEventListener('popstate', () => {
        const confirmed = window.confirm('Meninggalkan halaman admin akan logout. Lanjutkan?');

        if (confirmed) {
            logoutForm.submit();
            return;
        }

        window.history.pushState({ guard: 'admin-back-logout' }, '', window.location.href);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminBackLogoutGuard);
} else {
    initAdminBackLogoutGuard();
}
