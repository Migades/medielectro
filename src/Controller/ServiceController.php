<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ServiceController extends AbstractController
{
    #[Route('/reparacion', name: 'app_service_reparacion')]
    public function reparacion(): Response
    {
        return $this->render('service/reparacion.html.twig');
    }

    #[Route('/instalacion', name: 'app_service_instalacion')]
    public function instalacion(): Response
    {
        return $this->render('service/instalacion.html.twig');
    }
}
