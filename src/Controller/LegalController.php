<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/legal', name: 'legal_')]
class LegalController extends AbstractController
{
    #[Route('/aviso-legal', name: 'aviso', methods: ['GET'])]
    public function avisoLegal(): Response
    {
        return $this->render('legal/aviso-legal.html.twig');
    }

    #[Route('/privacidad', name: 'privacidad', methods: ['GET'])]
    public function privacidad(): Response
    {
        return $this->render('legal/privacidad.html.twig');
    }

    #[Route('/cookies', name: 'cookies', methods: ['GET'])]
    public function cookies(): Response
    {
        return $this->render('legal/cookies.html.twig');
    }
}
