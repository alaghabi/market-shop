<?php

namespace App\Tests\Entity;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class EmailVerificationTokenTest extends TestCase
{
    public function testTokenIsUsableUntilItExpiresOrIsUsed(): void
    {
        $token = new EmailVerificationToken(
            user: new User(null, 'employee@example.test'),
            boutique: null,
            tokenHash: hash('sha256', 'plain-token'),
            expiresAt: new \DateTimeImmutable('+1 hour'),
            createdAt: new \DateTimeImmutable(),
        );

        self::assertTrue($token->isUsable(new \DateTimeImmutable()));

        $token->markUsed();

        self::assertFalse($token->isUsable(new \DateTimeImmutable()));
    }

    public function testExpiredTokenIsNotUsable(): void
    {
        $token = new EmailVerificationToken(
            user: new User(null, 'employee@example.test'),
            boutique: null,
            tokenHash: hash('sha256', 'plain-token'),
            expiresAt: new \DateTimeImmutable('-1 second'),
            createdAt: new \DateTimeImmutable(),
        );

        self::assertFalse($token->isUsable(new \DateTimeImmutable()));
    }
}
