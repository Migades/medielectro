/**
 * Banner informativo de cookies – Medielectro
 * Solo cookies técnicas → banner informativo, no de consentimiento.
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'cookie_notice';
    var COOKIE_DAYS = 365;
    var BANNER_ID   = 'cookie-banner';

    function getCookie(name) {
        return document.cookie.split('; ').reduce(function (r, c) {
            var parts = c.split('=');
            return parts[0] === name ? parts[1] : r;
        }, null);
    }

    function setCookie(name, value, days) {
        var expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + value + '; expires=' + expires + '; path=/; SameSite=Lax';
    }

    function dismissBanner() {
        var banner = document.getElementById(BANNER_ID);
        if (!banner) return;
        banner.classList.add('cookie-banner--hiding');
        banner.addEventListener('transitionend', function () { banner.remove(); }, { once: true });
        setCookie(COOKIE_NAME, '1', COOKIE_DAYS);
    }

    function renderBanner() {
        var banner = document.createElement('div');
        banner.id = BANNER_ID;
        banner.setAttribute('role', 'region');
        banner.setAttribute('aria-label', 'Aviso de cookies');
        banner.innerHTML =
            '<p class="cookie-banner__text">' +
            'Este sitio utiliza únicamente <strong>cookies técnicas necesarias</strong> ' +
            'para su funcionamiento (carrito y sesión). No usamos cookies de análisis ni publicidad. ' +
            '<a href="/legal/cookies" class="cookie-banner__link">Más información</a>' +
            '</p>' +
            '<button id="cookie-banner-close" class="cookie-banner__btn">Entendido</button>';
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
