<?php


namespace App\Mail;

use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Servicio de envío de emails relacionados con pedidos.
 * Usa Symfony Mailer + plantillas Twig.
 *
 * Configurar MAILER_DSN en .env.local para producción:
 *   smtp://user:pass@smtp.example.com:587
 *   gmail+smtp://user:pass@default
 */
class OrderMailer
{
    // Email del negocio — configurable vía parámetro si hace falta
    private const FROM_EMAIL = 'pedidos@medielectro.es';
    private const FROM_NAME = 'Medielectro';
    private const ADMIN_EMAIL = 'info@medielectro.es';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment     $twig,
    )
    {
    }

    /**
     * Email de confirmación al cliente.
     * Se llama justo tras persistir el pedido en BD.
     */
    public function sendOrderConfirmation(Order $order): void
    {
        $customer = $order->getCustomer();

        $html = $this->twig->render('mail/order_confirmation.html.twig', [
            'order' => $order,
            'customer' => $customer,
        ]);

        $email = (new Email())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to(new Address($customer->getEmail(), $customer->getName()))
            ->replyTo(self::ADMIN_EMAIL)
            ->subject(sprintf('Pedido %s recibido · Medielectro', $order->getReference()))
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * Notificación interna al equipo de Medielectro.
     * Se llama tras el mismo evento que sendOrderConfirmation.
     */
    public function sendInternalNotification(Order $order): void
    {
        $customer = $order->getCustomer();

        $html = $this->twig->render('mail/order_internal.html.twig', [
            'order' => $order,
            'customer' => $customer,
        ]);

        $email = (new Email())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to(self::ADMIN_EMAIL)
            ->subject(sprintf('[Nuevo pedido] %s — %s €', $order->getReference(), $order->getTotal()))
            ->html($html);

        $this->mailer->send($email);
    }
}
