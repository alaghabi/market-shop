<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/** @implements AttributesBasedUserProviderInterface<User|InMemoryUser> */
final class UserProvider implements AttributesBasedUserProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
    ) {
    }

    /** @param array<string, mixed> $attributes */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface
    {
        if ('' === $identifier) {
            throw new UserNotFoundException('Empty identifier.');
        }

        $isOidc = isset($attributes['iss'], $attributes['sub']);
        if ($isOidc && true !== ($attributes['email_verified'] ?? false)) {
            throw new UserNotFoundException('Keycloak email is not verified.');
        }

        $user = null;
        $subject = $attributes['sub'] ?? null;
        if (is_string($subject) && '' !== $subject) {
            $user = $this->users->findOneBy(['keycloakSubject' => $subject]);
        }

        if (!$user instanceof User) {
            $user = $this->users->findOneBy(['identifier' => $identifier]);
        }

        if ($user instanceof User) {
            if (is_string($subject) && '' !== $subject && null === $user->getKeycloakSubject()) {
                $user->setKeycloakSubject($subject);
                $this->entityManager->flush();
            }

            if (\App\Enum\UserStatus::Suspended === $user->getStatus() || $user->isDeleted()) {
                throw new UserNotFoundException('User is not active.');
            }

            return $user;
        }

        if (!$this->isKeycloakCustomer($attributes)) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        if (!is_string($subject) || '' === $subject) {
            throw new UserNotFoundException('Keycloak subject is missing.');
        }

        $user = new User(
            boutique: null,
            identifier: strtolower($identifier),
            roles: ['ROLE_CUSTOMER'],
            displayName: $this->stringClaim($attributes, 'name'),
            status: \App\Enum\UserStatus::Active,
        );
        $user->setKeycloakSubject($subject);
        $user->markEmailVerified();
        $user->setFirstname($this->stringClaim($attributes, 'given_name'));
        $user->setLastname($this->stringClaim($attributes, 'family_name'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class) || InMemoryUser::class === $class;
    }

    /** @param array<string, mixed> $attributes */
    private function isKeycloakCustomer(array $attributes): bool
    {
        $roles = $attributes['realm_access']['roles'] ?? [];
        if (!is_array($roles)) {
            $roles = [];
        }

        $clientRoles = $attributes['resource_access'] ?? [];
        if (is_array($clientRoles)) {
            foreach ($clientRoles as $client) {
                if (is_array($client['roles'] ?? null)) {
                    $roles = [...$roles, ...$client['roles']];
                }
            }
        }

        $normalizedRoles = array_map('strtoupper', array_filter($roles, 'is_string'));

        return in_array('CUSTOMER', $normalizedRoles, true)
            || in_array('ROLE_CUSTOMER', $normalizedRoles, true);
    }

    /** @param array<string, mixed> $attributes */
    private function stringClaim(array $attributes, string $claim): ?string
    {
        $value = $attributes[$claim] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
