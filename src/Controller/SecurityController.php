<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SecurityController extends AbstractController
{
    #[Route('/app-login', name: 'app_login')]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher
    ): Response {
        // Already logged in via session → go straight to forum
        if ($this->getUser()) {
            return $this->redirectToRoute('app_forum_home');
        }

        // Try to auto-login from the JWT cookie set by the front-end
        $jwtCookie = $request->cookies->get('finovate_token');
        if ($jwtCookie) {
            try {
                $payload = $jwtManager->parse($jwtCookie);
                $email   = $payload['username'] ?? $payload['email'] ?? null;

                if ($email) {
                    $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($user) {
                        // Create a session token and log the user in
                        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                        $this->container->get('security.token_storage')->setToken($token);
                        $request->getSession()->set('_security_main', serialize($token));

                        return $this->redirectToRoute('app_forum_home');
                    }
                }
            } catch (\Throwable) {
                // Invalid/expired JWT — fall through to show login form
            }
        }

        $error        = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/app-logout', name: 'app_logout')]
    public function logout(): void
    {
        // Symfony handles this automatically
    }
}
