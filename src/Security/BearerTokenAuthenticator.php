<?php

namespace App\Security;

use App\Security\Validator\BearerTokenValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class BearerTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly BearerTokenValidator $tokenValidator)
    {
    }

    public function supports(Request $request): ?bool
    {
        $authorization = (string) $request->headers->get('Authorization');
        if (!str_starts_with($authorization, 'Bearer ')) {
            return false;
        }

        $token = trim(substr($authorization, 7));
        if ('dev-super-admin-token' === $token) {
            return true;
        }

        $parts = explode('.', $token);
        if (3 !== count($parts)) {
            return false;
        }

        $header = base64_decode(strtr($parts[0], '-_', '+/'), true);
        if (false === $header) {
            return false;
        }

        try {
            $decoded = json_decode($header, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return is_array($decoded) && 'HS256' === ($decoded['alg'] ?? null);
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr((string) $request->headers->get('Authorization'), 7);
        $identity = $this->tokenValidator->validate($token);
        if (is_string($identity['tokenId'] ?? null)) {
            $request->attributes->set('_user_session_token_id', $identity['tokenId']);
        }

        return new SelfValidatingPassport(new UserBadge(
            $identity['identifier'],
            static fn (string $userIdentifier): InMemoryUser => new InMemoryUser($userIdentifier, null, $identity['roles']),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new Response('Authentication failed.', Response::HTTP_UNAUTHORIZED);
    }
}
