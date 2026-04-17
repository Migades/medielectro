/**
 * Banner informativo de cookies – Medielectro
 * Solo cookies técnicas → banner informativo, no de consentimiento.
 */
(function () {
    'use strict';

    const COOKIE_NAME = 'cookie_notice';
    const COOKIE_DAYS = 365;
    const BANNER_ID   = 'cookie-banner';

    function getCookie(name) {
        return document.cookie
            .split('; ')
            .find(row => row.startsWith(name + '='))
            ?.split('=')[1] ?? null;
    }

    function setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${name}=${value}; expires=${expires}; path=/; SameSite=Lax`;
    }

    function dismissBanner() {
        const banner = document.getElementById(BANNER_ID);
        if (!banner) return;
        banner.classList.add('cookie-banner--hiding');
        banner.addEventListener('transitionend', () => banner.remove(), { once: true });
        setCookie(COOKIE_NAME, '1', COOKIE_DAYS);
    }

    function renderBanner() {
        const banner = document.createElement('div');
        banner.id = BANNER_ID;
        banner.setAttribute('role', 'region');
        banner.setAttribute('aria-label', 'Aviso de cookies');
        banner.innerHTML = `
            <p class="cookie-banner__text">
                Este sitio utiliza únicamente <strong>cookies técnicas necesarias</strong>
                para su funcionamiento (carrito y sesión). No usamos cookies de análisis ni publicidad.
                <a href="/legal/cookies" class="cookie-banner__link">Más información</a>
            </p>
            <button id="cookie-banner-close" class="cookie-banner__btn" aria-label="Cerrar aviso de cookies">
                Entendido
            </button>
        `;
        document.body.appendChild(banner);
        document.getElementById('cookie-banner-close').addEventListener('click', dismissBanner);
    }

    function init() {
        if (getCookie(COOKIE_NAME) === '1') return;
        setTimeout(renderBanner, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
