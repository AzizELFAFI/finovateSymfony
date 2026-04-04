<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontController extends AbstractController
{
    #[Route('/', name: 'front_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('front/index.html.twig');
    }

    #[Route('/about', name: 'front_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }

    #[Route('/services', name: 'front_services', methods: ['GET'])]
    public function services(): Response
    {
        return $this->render('front/services.html.twig');
    }

    #[Route('/one-page', name: 'front_one_page', methods: ['GET'])]
    public function onePage(): Response
    {
        return $this->render('front/one-page.html.twig');
    }

    #[Route('/signup', name: 'front_signup', methods: ['GET'])]
    public function signup(): Response
    {
        return $this->render('front/signup.html.twig');
    }

    #[Route('/login', name: 'front_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('front/login.html.twig');
    }

    #[Route('/logout', name: 'front_logout', methods: ['GET'])]
    public function logout(): Response
    {
        $response = $this->render('front/logout.html.twig');
        $response->headers->setCookie(Cookie::create('finovate_token')->withValue('')->withExpires(1)->withPath('/'));
        return $response;
    }

    #[Route('/contact', name: 'front_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('front/contact.html.twig');
    }

    #[Route('/user/dashboard', name: 'user_dashboard', methods: ['GET'])]
    public function userDashboard(): Response
    {
        return $this->render('front/dashboard.html.twig');
    }
}
