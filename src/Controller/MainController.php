<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class MainController extends AbstractController
{
    /**
     * Matches / exactly
     *
     * @Route("/", name="index")
     */

    public function index()
    {
      return $this->render('index.html.twig');

    }

    public function notFound($slug)
    {
      return new Response(
        'url ' . $slug . ' NOT FOUND'
      );
    }

}

?>
