<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Only activate on the Google callback route
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $googleId = $googleUser->getId();
                $email = $googleUser->getEmail();
                $name = $googleUser->getName();

                // 1. Check if user exists by Google ID
                $existingUser = $this->userRepository->findOneBy(['googleId' => $googleId]);
                if ($existingUser) {
                    return $existingUser;
                }

                // 2. Check if user exists by email
                $existingUser = $this->userRepository->findOneBy(['email' => $email]);
                if ($existingUser) {
                    // Link Google account to existing user
                    $existingUser->setGoogleId($googleId);
                    $this->entityManager->flush();
                    return $existingUser;
                }

                // 3. Check if user exists by username (using email prefix)
                $username = explode('@', $email)[0];
                $existingUser = $this->userRepository->findOneBy(['username' => $username]);
                if ($existingUser) {
                    $existingUser->setGoogleId($googleId);
                    $existingUser->setEmail($email);
                    $this->entityManager->flush();
                    return $existingUser;
                }

                // 4. Create new user from Google data
                $user = new User();
                $user->setUsername($username);
                $user->setEmail($email);
                $user->setGoogleId($googleId);
                // Set a random password since Google users don't need one
                $user->setPassword(bin2hex(random_bytes(32)));
                $user->setRoles(['ROLE_STAFF']);

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('admin_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new RedirectResponse(
            $this->router->generate('app_login', ['error' => $message])
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            $this->router->generate('app_login'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}
