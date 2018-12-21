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

    public function about()
    {
      return $this->render('about.html.twig');
    }

    public function contact() {
      return $this->render('contact.html.twig');
    }

    public function categories() {
      return $this->render('categories.html.twig');
    }

    public function library() {
      return $this->render('library.html.twig');
    }

    public function notFound($slug)
    {
      return $this->render('404.html.twig');
    }


}

?>
