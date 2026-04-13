<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\GoogleCompleteProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleProfileController extends AbstractController
{
    private function toSha256Hex(string $input): string
    {
        return hash('sha256', $input);
    }

    #[Route('/connect/google/complete-profile', name: 'google_complete_profile', methods: ['GET', 'POST'])]
    public function completeProfile(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
    ): Response {
        $email = $session->get('google_oauth_email');
        if (!is_string($email) || trim($email) === '') {
            $this->addFlash('danger', 'Session Google expirée. Veuillez réessayer.');
            return $this->redirectToRoute('front_login');
        }

        $email = trim($email);
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing instanceof User) {
            // Si finalement le compte existe, on le connecte
            $jwt = $jwtManager->create($existing);
            $session->remove('google_oauth_email');
            $session->remove('google_oauth_picture');

            return new RedirectResponse($this->generateUrl('oauth_success', [
                'token' => $jwt,
                'redirect' => $this->generateUrl('user_dashboard'),
            ]));
        }

        $form = $this->createForm(GoogleCompleteProfileType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $cin = trim((string) ($data['cin'] ?? ''));
            $phone = (int) ($data['phone_number'] ?? 0);

            $cinExists = $em->getRepository(User::class)->findOneBy(['cin' => $cin]);
            if ($cinExists instanceof User) {
                $form->get('cin')->addError(new \Symfony\Component\Form\FormError('Ce CIN est déjà utilisé.'));
            }

            $phoneExists = $em->getRepository(User::class)->findOneBy(['phone_number' => $phone]);
            if ($phoneExists instanceof User) {
                $form->get('phone_number')->addError(new \Symfony\Component\Form\FormError('Ce numéro de téléphone est déjà utilisé.'));
            }

            if ($form->isValid()) {
                $user = new User();
                $user->setId((string) (int) (microtime(true) * 1000));
                $user->setEmail($email);
                $user->setFirstname((string) $data['firstname']);
                $user->setLastname((string) $data['lastname']);
                $user->setBirthdate($data['birthdate']);
                $user->setCin($cin);
                $user->setPhone_number($phone);
                $user->setRole('USER');
                $user->setPoints(0);
                $user->setSolde('0');
                $user->setCreated_at(new \DateTime());

                // numero_carte unique best-effort
                do {
                    $card = (string) random_int(1000000000000000, 9999999999999999);
                    $cardExists = $em->getRepository(User::class)->findOneBy(['numero_carte' => $card]);
                } while ($cardExists instanceof User);

                $user->setNumero_carte($card);

                // password random (sha256)
                $user->setPassword($this->toSha256Hex(bin2hex(random_bytes(16))));

                $em->persist($user);
                $em->flush();

                $session->remove('google_oauth_email');
                $session->remove('google_oauth_picture');

                $jwt = $jwtManager->create($user);

                return new RedirectResponse($this->generateUrl('oauth_success', [
                    'token' => $jwt,
                    'redirect' => $this->generateUrl('user_dashboard'),
                ]));
            }
        }

        return $this->render('front/google_complete_profile.html.twig', [
            'form' => $form,
            'email' => $email,
        ]);
    }

    #[Route('/oauth/success', name: 'oauth_success', methods: ['GET'])]
    public function oauthSuccess(Request $request): Response
    {
        $token = (string) $request->query->get('token', '');
        $redirect = (string) $request->query->get('redirect', '/user/dashboard');

        return $this->render('front/oauth_success.html.twig', [
            'token' => $token,
            'redirect' => $redirect,
        ]);
    }
}
