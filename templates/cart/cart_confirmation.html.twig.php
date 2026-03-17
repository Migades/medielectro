{% extends 'base.html.twig' %}
{% block title %}Pedido confirmado — Medielectro{% endblock %}

{% block body %}
<style>
    .confirmation {
        max-width: 560px;
        margin: 60px auto;
        text-align: center;
        padding: 48px 32px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 20px;
    }

    .confirmation__icon {
        width: 72px; height: 72px;
        border-radius: 50%;
        background: #effbf3;
        border: 2px solid #b7ebc6;
        display: flex; align-items: center; justify-content: center;
        font-size: 32px;
        margin: 0 auto 20px;
    }

    .confirmation h1 { font-size: 24px; font-weight: 800; margin: 0 0 10px; letter-spacing: -.3px; }
    .confirmation p  { font-size: 14px; color: var(--muted); margin: 0 0 24px; line-height: 1.6; }

    .confirmation__actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
</style>

<div class="confirmation">
    <div class="confirmation__icon">✓</div>
    <h1>¡Pedido recibido!</h1>
    <p>
        Hemos registrado tu pedido correctamente.<br>
        Recibirás una confirmación en tu email en breve.<br>
        Si tienes dudas, llámanos al <strong>96 350 16 75</strong>.
    </p>
    <div class="confirmation__actions">
        <a class="btn btn-primary" href="{{ path('app_catalog') }}">Seguir comprando</a>
        <a class="btn" href="{{ path('app_home') }}">Ir al inicio</a>
    </div>
</div>
{% endblock %}
