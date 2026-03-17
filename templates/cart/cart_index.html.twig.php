{% extends 'base.html.twig' %}
{% block title %}Carrito — Medielectro{% endblock %}

{% block body %}
<style>
    .cart-layout {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 20px;
        align-items: start;
        margin: 24px 0;
    }

    .cart-title { font-size: 22px; font-weight: 800; margin: 0 0 16px; letter-spacing: -.3px; }

    /* Líneas del carrito */
    .cart-items { display: flex; flex-direction: column; gap: 10px; }

    .cart-row {
        display: flex;
        align-items: center;
        gap: 14px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 14px;
    }

    .cart-row__img {
        width: 72px; height: 72px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid var(--border);
        overflow: hidden;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
    }

    .cart-row__img img { width: 100%; height: 100%; object-fit: cover; }
    .cart-row__placeholder { font-size: 24px; color: #c8d0da; }

    .cart-row__info { flex: 1; min-width: 0; }
    .cart-row__brand { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; color: var(--muted); }
    .cart-row__title { font-size: 14px; font-weight: 700; line-height: 1.3; margin: 2px 0; }
    .cart-row__ref   { font-size: 12px; color: var(--muted); }

    .cart-row__qty {
        display: flex;
        align-items: center;
        gap: 0;
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        flex-shrink: 0;
    }

    .qty-btn {
        width: 34px; height: 34px;
        border: none; background: #f9fafb;
        font-size: 16px; font-weight: 700;
        cursor: pointer; color: var(--text);
        transition: background .15s;
        display: flex; align-items: center; justify-content: center;
    }

    .qty-btn:hover { background: #f3f4f6; }

    .qty-input {
        width: 40px; height: 34px;
        border: none; border-left: 1px solid var(--border); border-right: 1px solid var(--border);
        text-align: center; font-size: 14px; font-weight: 700;
        background: white; color: var(--text);
        -moz-appearance: textfield;
    }

    .qty-input::-webkit-outer-spin-button,
    .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; }

    .cart-row__subtotal { font-size: 16px; font-weight: 900; letter-spacing: -.3px; min-width: 80px; text-align: right; }

    .cart-row__remove {
        background: none; border: none; color: var(--muted);
        cursor: pointer; padding: 6px; border-radius: 8px;
        transition: background .15s, color .15s;
        display: flex; align-items: center;
    }

    .cart-row__remove:hover { background: #fff1f2; color: var(--brand); }

    /* Resumen lateral */
    .cart-summary {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 20px;
        position: sticky;
        top: 168px;
    }

    .cart-summary__title { font-size: 15px; font-weight: 800; margin: 0 0 16px; }

    .cart-summary__row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        color: var(--muted);
        margin-bottom: 8px;
    }

    .cart-summary__total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 18px;
        font-weight: 900;
        margin: 12px 0 16px;
        padding-top: 12px;
        border-top: 1px solid var(--border);
    }

    .cart-summary__cta {
        display: block;
        width: 100%;
        background: var(--brand);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 13px;
        font-size: 15px;
        font-weight: 700;
        text-align: center;
        text-decoration: none;
        cursor: pointer;
        transition: opacity .15s;
    }

    .cart-summary__cta:hover { opacity: .88; }

    .cart-empty {
        text-align: center;
        padding: 60px 24px;
        color: var(--muted);
    }

    .cart-empty__icon { font-size: 48px; margin-bottom: 16px; }
    .cart-empty__title { font-size: 18px; font-weight: 700; color: var(--text); margin-bottom: 8px; }
    .cart-empty__sub   { font-size: 14px; margin-bottom: 20px; }

    @media (max-width: 768px) {
        .cart-layout { grid-template-columns: 1fr; }
        .cart-summary { position: static; }
        .cart-row__subtotal { min-width: 60px; font-size: 14px; }
    }
</style>

<div style="margin: 24px 0 0;">
    <h1 class="cart-title">
        Carrito
        {% if items|length > 0 %}
        <span style="font-size:15px; font-weight:400; color:var(--muted);">({{ items|length }} {% if items|length == 1 %}producto{% else %}productos{% endif %})</span>
        {% endif %}
    </h1>

    {% for message in app.flashes('success') %}
    <div style="background:#effbf3; border:1px solid #b7ebc6; border-radius:10px; padding:12px 16px; margin-bottom:12px; font-size:14px; color:#15803d;">
        {{ message }}
    </div>
    {% endfor %}

    {% if items|length == 0 %}
    <div class="cart-empty">
        <div class="cart-empty__icon">🛒</div>
        <div class="cart-empty__title">Tu carrito está vacío</div>
        <div class="cart-empty__sub">Añade productos desde el catálogo para empezar.</div>
        <a class="btn btn-primary" href="{{ path('app_catalog') }}">Ver catálogo</a>
    </div>
    {% else %}
    <div class="cart-layout">
        <div class="cart-items" id="cartItems">
            {% for item in items %}
            <div class="cart-row" data-article="{{ item.article }}" id="row-{{ item.article }}">
                <div class="cart-row__img">
                    {% if item.image %}
                    <img src="{{ item.image }}" alt="{{ item.title }}">
                    {% else %}
                    <div class="cart-row__placeholder">📦</div>
                    {% endif %}
                </div>

                <div class="cart-row__info">
                    {% if item.brand %}<div class="cart-row__brand">{{ item.brand }}</div>{% endif %}
                    <div class="cart-row__title">{{ item.title }}</div>
                    <div class="cart-row__ref">Ref. {{ item.article }} · {{ item.price|number_format(2, ',', '.') }} € / ud.</div>
                </div>

                <div class="cart-row__qty">
                    <button class="qty-btn" onclick="changeQty('{{ item.article }}', -1)">−</button>
                    <input class="qty-input" type="number" min="1" max="999"
                           value="{{ item.quantity }}"
                           id="qty-{{ item.article }}"
                           onchange="setQty('{{ item.article }}', this.value)">
                    <button class="qty-btn" onclick="changeQty('{{ item.article }}', 1)">+</button>
                </div>

                <div class="cart-row__subtotal" id="subtotal-{{ item.article }}">
                    {{ item.subtotal|number_format(2, ',', '.') }} €
                </div>

                <form method="post" action="{{ path('app_cart_remove', {article: item.article}) }}" style="margin:0;">
                    <input type="hidden" name="_token" value="{{ csrf_token('cart_remove_' ~ item.article) }}">
                    <button type="submit" class="cart-row__remove" title="Eliminar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3,6 5,6 21,6"/><path d="M19,6l-1,14H6L5,6"/><path d="M10,11v6M14,11v6"/><path d="M9,6V4h6v2"/>
                        </svg>
                    </button>
                </form>
            </div>
            {% endfor %}
        </div>

        <aside class="cart-summary" id="cartSummary">
            <div class="cart-summary__title">Resumen del pedido</div>
            <div class="cart-summary__row">
                <span>Subtotal</span>
                <span id="summaryTotal">{{ total|number_format(2, ',', '.') }} €</span>
            </div>
            <div class="cart-summary__row">
                <span>Envío</span>
                <span style="color:#15803d; font-weight:600;">A consultar</span>
            </div>
            <div class="cart-summary__total">
                <span>Total</span>
                <span id="summaryTotalFinal">{{ total|number_format(2, ',', '.') }} €</span>
            </div>
            <a class="cart-summary__cta" href="{{ path('app_cart_checkout') }}">
                Tramitar pedido →
            </a>
            <div style="margin-top:12px; text-align:center;">
                <a href="{{ path('app_catalog') }}" style="font-size:13px; color:var(--muted);">← Seguir comprando</a>
            </div>
        </aside>
    </div>
    {% endif %}
</div>

<script>
    // Actualizar cantidad vía XHR — sin recargar página
    function changeQty(article, delta) {
        var input = document.getElementById('qty-' + article);
        var newVal = Math.max(1, parseInt(input.value) + delta);
        input.value = newVal;
        updateQty(article, newVal);
    }

    function setQty(article, val) {
        var newVal = Math.max(1, parseInt(val) || 1);
        updateQty(article, newVal);
    }

    function updateQty(article, quantity) {
        var formData = new FormData();
        formData.append('quantity', quantity);

        fetch('/carrito/actualizar/' + article, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        })
            .then(r => r.json())
            .then(data => {
                // Actualizar subtotal de la línea
                var subtotalEl = document.getElementById('subtotal-' + article);
                if (subtotalEl) subtotalEl.textContent = data.subtotal + ' €';

                // Actualizar totales del resumen
                document.getElementById('summaryTotal').textContent      = data.total + ' €';
                document.getElementById('summaryTotalFinal').textContent = data.total + ' €';

                // Actualizar badge del header
                updateCartBadge(data.count);
            });
    }

    function updateCartBadge(count) {
        var badge = document.getElementById('cartBadge');
        if (!badge) return;
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
</script>
{% endblock %}
