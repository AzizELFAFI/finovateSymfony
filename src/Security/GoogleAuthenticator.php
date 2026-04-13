<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        $session = $request->getSession();

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $session) {
                /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = (string) $googleUser->getEmail();
                $email = trim($email);

                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if ($user instanceof User) {
                    return $user;
                }

                // Pas de création ici: User a des contraintes NotBlank (firstname, lastname, etc.)
                // On passe par la page "compléter profil".
                $session->set('google_oauth_email', $email);
                $session->set('google_oauth_picture', $googleUser->getAvatar() ?: null);

                // On empêche la création d'un token authentifié (sinon Symfony tente de sérialiser l'utilisateur en session).
                // La redirection vers la page de complétion se fait dans onAuthenticationFailure().
                throw new CustomUserMessageAuthenticationException('GOOGLE_PROFILE_INCOMPLETE');
            })
        );
    }

    private function isProfileComplete(User $user): bool
    {
        // Heuristique: si cin/phone/birthdate existent et sont valides.
        // Ici, comme l'entité impose NotBlank, un user existant doit être complet.
        // Mais on garde la fonction pour extension future.
        try {
            $cin = $user->getCin();
            $phone = $user->getPhone_number();
            $birth = $user->getBirthdate();
        } catch (\Throwable) {
            return false;
        }

        return $cin !== '' && $phone > 0 && $birth instanceof \DateTimeInterface;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new RedirectResponse($this->router->generate('front_login'));
        }

        $jwt = $this->jwtManager->create($user);
        $url = $this->router->generate('oauth_success', [
            'token' => $jwt,
            'redirect' => $this->router->generate('user_dashboard'),
        ]);

        return new RedirectResponse($url);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception instanceof CustomUserMessageAuthenticationException
            && $exception->getMessageKey() === 'GOOGLE_PROFILE_INCOMPLETE') {
            return new RedirectResponse($this->router->generate('google_complete_profile'));
        }

        return new RedirectResponse($this->router->generate('front_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('front_login'));
    }
}
