<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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

    #[Route('/contact/send', name: 'front_contact_send', methods: ['POST'])]
    public function sendContact(Request $request, MailerInterface $mailer): Response
    {
        if (!$this->isCsrfTokenValid('contact_form', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire. Veuillez réessayer.');
            return $this->redirectToRoute('front_contact');
        }

        $name = trim((string) $request->request->get('name'));
        $fromEmail = trim((string) $request->request->get('email'));
        $subject = trim((string) $request->request->get('subject'));
        $message = trim((string) $request->request->get('message'));

        if ($name === '' || $fromEmail === '' || $subject === '' || $message === '') {
            $this->addFlash('danger', 'Veuillez remplir tous les champs du formulaire.');
            return $this->redirectToRoute('front_contact');
        }

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse e-mail invalide.');
            return $this->redirectToRoute('front_contact');
        }

        $email = (new Email())
            ->from('no-reply@finovate.tn')
            ->to('aziz.fafi@gmail.com')
            ->replyTo($fromEmail)
            ->subject('[Contact Finovate] ' . $subject)
            ->text("Nom: {$name}\nEmail: {$fromEmail}\n\nMessage:\n{$message}\n");

        try {
            $mailer->send($email);
            $this->addFlash('success', 'Votre message a été envoyé avec succès.');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('danger', "Impossible d'envoyer l'e-mail pour le moment. Veuillez réessayer plus tard.");
        }

        return $this->redirectToRoute('front_contact');
    }

    #[Route('/user/dashboard', name: 'user_dashboard', methods: ['GET'])]
    public function userDashboard(): Response
    {
        return $this->render('front/dashboard.html.twig');
    }
}
