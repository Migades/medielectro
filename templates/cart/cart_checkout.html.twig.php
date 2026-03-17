{% extends 'base.html.twig' %}
{% block title %}Checkout — Medielectro{% endblock %}

{% block body %}
<style>
    .checkout-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 20px;
        align-items: start;
        margin: 24px 0;
    }

    .checkout-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 24px;
    }

    .checkout-card__title {
        font-size: 16px;
        font-weight: 800;
        margin: 0 0 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--border);
    }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
    .form-group label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; }

    .form-group input,
    .form-group textarea,
    .form-group select {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 14px;
        color: var(--text);
        background: #f9fafb;
        outline: none;
        transition: border-color .15s, background .15s;
        width: 100%;
    }

    .form-group input:focus,
    .form-group textarea:focus { border-color: var(--brand); background: white; }

    .form-group textarea { resize: vertical; min-height: 80px; }

    .form-group--full { grid-column: 1 / -1; }

    /* Resumen de pedido lateral */
    .order-summary { position: sticky; top: 168px; }

    .summary-items { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }

    .summary-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
    }

    .summary-item__img {
        width: 48px; height: 48px;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid var(--border);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .summary-item__img img { width: 100%; height: 100%; object-fit: cover; }

    .summary-item__info { flex: 1; min-width: 0; }
    .summary-item__title { font-weight: 700; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .summary-item__qty   { font-size: 12px; color: var(--muted); }
    .summary-item__price { font-weight: 800; white-space: nowrap; }

    .summary-divider { border: none; border-top: 1px solid var(--border); margin: 12px 0; }

    .summary-row {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        color: var(--muted);
        margin-bottom: 6px;
    }

    .summary-total {
        display: flex;
        justify-content: space-between;
        font-size: 18px;
        font-weight: 900;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 2px solid var(--border);
    }

    .btn-checkout {
        display: block;
        width: 100%;
        background: var(--brand);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 14px;
        font-size: 15px;
        font-weight: 700;
        text-align: center;
        cursor: pointer;
        margin-top: 14px;
        transition: opacity .15s;
    }

    .btn-checkout:hover { opacity: .88; }

    .step-indicator {
        display: flex;
        align-items: center;
        gap: 0;
        margin-bottom: 24px;
    }

    .step {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--muted);
    }

    .step__num {
        width: 26px; height: 26px;
        border-radius: 50%;
        background: #f3f4f6;
        border: 2px solid var(--border);
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 800;
    }

    .step--active .step__num { background: var(--brand); border-color: var(--brand); color: white; }
    .step--active { color: var(--text); }

    .step-line { flex: 1; height: 2px; background: var(--border); margin: 0 10px; max-width: 40px; }

    @media (max-width: 768px) {
        .checkout-layout { grid-template-columns: 1fr; }
        .order-summary { position: static; }
        .form-row { grid-template-columns: 1fr; }
    }
</style>

<div style="margin: 24px 0 0;">
    <h1 style="font-size:22px; font-weight:800; margin:0 0 8px; letter-spacing:-.3px;">Tramitar pedido</h1>

    {# Indicador de pasos #}
    <div class="step-indicator">
        <div class="step step--active">
            <div class="step__num">1</div>
            <span>Tus datos</span>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step__num">2</div>
            <span>Pago</span>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step__num">3</div>
            <span>Confirmación</span>
        </div>
    </div>

    {% for message in app.flashes('error') %}
    <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:10px; padding:12px 16px; margin-bottom:14px; font-size:14px; color:var(--brand);">
        {{ message }}
    </div>
    {% endfor %}

    <div class="checkout-layout">
        {# Formulario de datos #}
        <div>
            <form method="post" action="{{ path('app_cart_process') }}">
                <div class="checkout-card" style="margin-bottom:14px;">
                    <div class="checkout-card__title">Datos de contacto</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre completo *</label>
                            <input type="text" name="name" required placeholder="Tu nombre y apellidos">
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required placeholder="tu@email.com">
                        </div>
                        <div class="form-group">
                            <label>Teléfono *</label>
                            <input type="tel" name="phone" required placeholder="6XX XXX XXX">
                        </div>
                        <div class="form-group">
                            <label>CIF / NIF (opcional)</label>
                            <input type="text" name="nif" placeholder="Para factura">
                        </div>
                    </div>
                </div>

                <div class="checkout-card" style="margin-bottom:14px;">
                    <div class="checkout-card__title">Dirección de entrega</div>
                    <div class="form-group form-group--full">
                        <label>Dirección completa *</label>
                        <input type="text" name="address" required placeholder="Calle, número, piso…">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Código postal *</label>
                            <input type="text" name="postal_code" required placeholder="46001">
                        </div>
                        <div class="form-group">
                            <label>Localidad *</label>
                            <input type="text" name="city" required placeholder="Valencia">
                        </div>
                    </div>
                </div>

                <div class="checkout-card">
                    <div class="checkout-card__title">Notas del pedido</div>
                    <div class="form-group">
                        <label>Instrucciones adicionales (opcional)</label>
                        <textarea name="notes" placeholder="Horario de entrega preferido, instrucciones de acceso…"></textarea>
                    </div>
                    <button type="submit" class="btn-checkout">
                        Continuar al pago →
                    </button>
                </div>
            </form>
        </div>

        {# Resumen del pedido #}
        <aside class="order-summary">
            <div class="checkout-card">
                <div class="checkout-card__title">Tu pedido</div>

                <div class="summary-items">
                    {% for item in items %}
                    <div class="summary-item">
                        <div class="summary-item__img">
                            {% if item.image %}
                            <img src="{{ item.image }}" alt="{{ item.title }}">
                            {% else %}
                            📦
                            {% endif %}
                        </div>
                        <div class="summary-item__info">
                            <div class="summary-item__title">{{ item.title|u.truncate(40, '…') }}</div>
                            <div class="summary-item__qty">× {{ item.quantity }}</div>
                        </div>
                        <div class="summary-item__price">{{ item.subtotal|number_format(2, ',', '.') }} €</div>
                    </div>
                    {% endfor %}
                </div>

                <hr class="summary-divider">

                <div class="summary-row"><span>Subtotal</span><span>{{ total|number_format(2, ',', '.') }} €</span></div>
                <div class="summary-row"><span>Envío</span><span style="color:#15803d;">A consultar</span></div>
                <div class="summary-total">
                    <span>Total</span>
                    <span>{{ total|number_format(2, ',', '.') }} €</span>
                </div>

                <div style="margin-top:14px; text-align:center;">
                    <a href="{{ path('app_cart_index') }}" style="font-size:13px; color:var(--muted);">← Editar carrito</a>
                </div>
            </div>

            {# Trust badges #}
            <div style="margin-top:12px; display:flex; flex-direction:column; gap:8px;">
                <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--muted);">
                    <span>🔒</span> Pago seguro con Stripe
                </div>
                <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--muted);">
                    <span>📞</span> ¿Dudas? Llámanos al 96 350 16 75
                </div>
            </div>
        </aside>
    </div>
</div>
{% endblock %}
