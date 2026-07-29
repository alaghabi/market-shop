<?php

namespace App\Security\Validator;

use App\Security\LocalTokenManager;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final readonly class BearerTokenValidator
{
    private const DEV_SUPER_ADMIN_TOKEN = 'dev-super-admin-token';

    public function __construct(
        private LocalTokenManager $localTokenManager,
        private readonly bool $kernelDebug = false,
    ) {
    }

    /** @return array{identifier: string, roles: list<string>, tokenId:?string} */
    public function validate(string $token): array
    {
        if ('' === trim($token)) {
            throw new BadCredentialsException('Empty bearer token.');
        }

        if (self::DEV_SUPER_ADMIN_TOKEN === $token) {
            if (!$this->kernelDebug) {
                throw new BadCredentialsException('Dev token rejected (kernel.debug = false).');
            }

            return [
                'identifier' => 'super-admin@market-shop.local',
                'roles' => ['ROLE_SUPER_ADMIN'],
                'tokenId' => null,
            ];
        }

        return $this->localTokenManager->validate($token);
    }
}
