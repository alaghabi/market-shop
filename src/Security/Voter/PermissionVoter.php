<?php

namespace App\Security\Voter;

use App\Security\Permission\PermissionAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/** @extends Voter<'PERMISSION', string> */
final class PermissionVoter extends Voter
{
    public function __construct(private readonly PermissionAccessService $permissions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        unset($subject);

        return 'PERMISSION' === $attribute;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return 'PERMISSION' === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        unset($token, $vote);

        if ('PERMISSION' !== $attribute || !is_string($subject) || '' === trim($subject)) {
            return false;
        }

        return $this->permissions->isGranted($subject);
    }
}
