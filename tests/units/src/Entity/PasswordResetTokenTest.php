<?php

namespace App\Tests\Entity;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenTest extends TestCase
{
    public function testTokenIsUsableUntilItExpiresOrIsUsed(): void
    {
        $token = new PasswordResetToken(
            new User(null, 'employee@example.test'),
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('+1 hour'),
        );

        self::assertTrue($token->isUsable(new \DateTimeImmutable()));

        $token->markUsed();

        self::assertFalse($token->isUsable(new \DateTimeImmutable()));
    }

    public function testExpiredTokenIsNotUsable(): void
    {
        $token = new PasswordResetToken(
            new User(null, 'employee@example.test'),
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('-1 second'),
        );

        self::assertFalse($token->isUsable(new \DateTimeImmutable()));
    }
}
