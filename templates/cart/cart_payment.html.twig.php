{% extends 'base.html.twig' %}
{% block title %}Pago seguro — Medielectro{% endblock %}

{% block body %}
<style>
    .payment-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 20px;
        align-items: start;
        margin: 24px 0;
    }

    .payment-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 24px;
    }

    .payment-card__title {
        font-size: 16px;
        font-weight: 800;
        margin: 0 0 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Stripe Elements container */
    #stripe-element {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 12px;
        background: #f9fafb;
        transition: border-color .15s;
    }

    #stripe-element.StripeElement--focus { border-color: var(--brand); background: white; }
    #stripe-element.StripeElement--invalid { border-color: #e30613; }

    #payment-errors {
        color: var(--brand);
        font-size: 13px;
        margin-top: 8px;
        min-height: 20px;
    }

    .btn-pay {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        background: var(--brand);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 14px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        margin-top: 16px;
        transition: opacity .15s;
    }

    .btn-pay:hover:not(:disabled) { opacity: .88; }
    .btn-pay:disabled { opacity: .6; cursor: not-allowed; }

    .btn-pay .spinner {
        display: none;
        width: 16px; height: 16px;
        border: 2px solid rgba(255,255,255,.4);
        border-top-color: white;
        border-radius: 50%;
        animation: spin .6s linear infinite;
    }

    .btn-pay.loading .spinner { display: block; }
    .btn-pay.loading .btn-text { display: none; }

    @keyframes spin { to { transform: rotate(360deg); } }

    .customer-summary {
        background: #f9fafb;
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 18px;
        font-size: 13px;
    }

    .customer-summary__row { display: flex; gap: 8px; margin-bottom: 4px; }
    .customer-summary__label { color: var(--muted); min-width: 70px; }
    .customer-summary__value { font-weight: 600; }

    /* Resumen lateral — igual que checkout */
    .summary-item { display: flex; align-items: center; gap: 10px; font-size: 13px; margin-bottom: 10px; }
    .summary-item__img { width: 44px; height: 44px; border-radius: 8px; background: #f8fafc; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
    .summary-item__img img { width: 100%; height: 100%; object-fit: cover; }
    .summary-item__info { flex: 1; min-width: 0; }
    .summary-item__title { font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .summary-item__qty { font-size: 12px; color: var(--muted); }
    .summary-item__price { font-weight: 800; white-space: nowrap; }
    .summary-total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 900; margin-top: 10px; padding-top: 10px; border-top: 2px solid var(--border); }

    .step-indicator { display: flex; align-items: center; gap: 0; margin-bottom: 24px; }
    .step { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--muted); }
    .step__num { width: 26px; height: 26px; border-radius: 50%; background: #f3f4f6; border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; }
    .step--active .step__num { background: var(--brand); border-color: var(--brand); color: white; }
    .step--done .step__num { background: #22c55e; border-color: #22c55e; color: white; }
    .step--active, .step--done { color: var(--text); }
    .step-line { flex: 1; height: 2px; background: var(--border); margin: 0 10px; max-width: 40px; }
    .step-line--done { background: #22c55e; }

    @media (max-width: 768px) {
        .payment-layout { grid-template-columns: 1fr; }
    }
</style>

<div style="margin: 24px 0 0;">
    <h1 style="font-size:22px; font-weight:800; margin:0 0 8px; letter-spacing:-.3px;">Pago seguro</h1>

    <div class="step-indicator">
        <div class="step step--done">
            <div class="step__num">✓</div>
            <span>Tus datos</span>
        </div>
        <div class="step-line step-line--done"></div>
        <div class="step step--active">
            <div class="step__num">2</div>
            <span>Pago</span>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step__num">3</div>
            <span>Confirmación</span>
        </div>
    </div>

    <div class="payment-layout">
        <div class="payment-card">
            <div class="payment-card__title">
                🔒 Datos de pago
                <span style="font-size:12px; font-weight:400; color:var(--muted); margin-left:auto;">Seguro con Stripe</span>
            </div>

            {# Resumen del cliente #}
            <div class="customer-summary">
                <div class="customer-summary__row">
                    <span class="customer-summary__label">Nombre</span>
                    <span class="customer-summary__value">{{ customer.name }}</span>
                </div>
                <div class="customer-summary__row">
                    <span class="customer-summary__label">Email</span>
                    <span class="customer-summary__value">{{ customer.email }}</span>
                </div>
                <div class="customer-summary__row">
                    <span class="customer-summary__label">Dirección</span>
                    <span class="customer-summary__value">{{ customer.address }}</span>
                </div>
            </div>

            <form id="payment-form">
                <label style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--muted); display:block; margin-bottom:8px;">
                    Tarjeta de crédito o débito
                </label>
                <div id="stripe-element"></div>
                <div id="payment-errors"></div>

                <button class="btn-pay" id="submit-btn" type="submit">
                    <span class="spinner"></span>
                    <span class="btn-text">Pagar {{ total|number_format(2, ',', '.') }} € →</span>
                </button>
            </form>
        </div>

        <aside>
            <div class="payment-card">
                <div class="payment-card__title" style="font-size:14px;">Resumen</div>

                {% for item in items %}
                <div class="summary-item">
                    <div class="summary-item__img">
                        {% if item.image %}<img src="{{ item.image }}" alt="">{% else %}📦{% endif %}
                    </div>
                    <div class="summary-item__info">
                        <div class="summary-item__title">{{ item.title|u.truncate(38, '…') }}</div>
                        <div class="summary-item__qty">× {{ item.quantity }}</div>
                    </div>
                    <div class="summary-item__price">{{ item.subtotal|number_format(2, ',', '.') }} €</div>
                </div>
                {% endfor %}

                <div class="summary-total">
                    <span>Total</span>
                    <span>{{ total|number_format(2, ',', '.') }} €</span>
                </div>
            </div>

            <div style="margin-top:12px; display:flex; flex-direction:column; gap:8px;">
                <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--muted);">🔒 Tus datos están cifrados con TLS</div>
                <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--muted);">💳 No almacenamos datos de tarjeta</div>
            </div>
        </aside>
    </div>
</div>

{# Stripe JS — cargado desde CDN oficial de Stripe #}
<script src="https://js.stripe.com/v3/"></script>
<script>
    (function() {
        var stripe = Stripe('{{ stripePublicKey }}');
        var elements = stripe.elements();

        var style = {
            base: {
                fontSize: '15px',
                color: '#111827',
                fontFamily: 'system-ui, -apple-system, sans-serif',
                '::placeholder': { color: '#9ca3af' },
            },
            invalid: { color: '#e30613' },
        };

        var card = elements.create('card', { style: style });
        card.mount('#stripe-element');

        card.on('change', function(event) {
            document.getElementById('payment-errors').textContent = event.error ? event.error.message : '';
        });

        var form = document.getElementById('payment-form');
        var btn  = document.getElementById('submit-btn');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            btn.classList.add('loading');
            btn.disabled = true;

            stripe.confirmCardPayment('{{ clientSecret }}', {
                payment_method: { card: card }
            }).then(function(result) {
                if (result.error) {
                    document.getElementById('payment-errors').textContent = result.error.message;
                    btn.classList.remove('loading');
                    btn.disabled = false;
                } else if (result.paymentIntent.status === 'succeeded') {
                    window.location.href = '{{ path('app_cart_confirmation') }}';
                }
            });
        });
    })();
</script>
{% endblock %}
