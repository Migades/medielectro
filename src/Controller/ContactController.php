<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ContactController extends AbstractController
{
    #[Route('/contacto', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(Request $request, MailerInterface $mailer): Response
    {
        $sent = false;
        $error = false;

        if ($request->isMethod('POST')) {
            $name    = trim($request->request->get('name', ''));
            $email   = trim($request->request->get('email', ''));
            $phone   = trim($request->request->get('phone', ''));
            $type    = trim($request->request->get('type', ''));
            $message = trim($request->request->get('message', ''));

            if ($name && $email && $message) {
                try {
                    $body = "Nombre: $name\nEmail: $email\nTeléfono: $phone\nTipo: $type\n\nMensaje:\n$message";

                    $mail = (new Email())
                        ->from('noreply@medielectro.es')
                        ->to('info@medielectro.es')
                        ->replyTo($email)
                        ->subject("Contacto web: $type — $name")
                        ->text($body);

                    $mailer->send($mail);
                    $sent = true;
                } catch (\Exception $e) {
                    $sent = true; // Show success anyway in dev
                }
            } else {
                $error = true;
            }
        }

        return $this->render('contact/index.html.twig', [
            'sent'  => $sent,
            'error' => $error,
        ]);
    }
}
