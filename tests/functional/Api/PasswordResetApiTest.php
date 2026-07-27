<?php

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;

final class PasswordResetApiTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testEmployeePasswordResetRequestIsAcceptedAndPersistsToken(): void
    {
        $email = 'employee.demo-hanooti@hanooti.local';
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['identifier' => $email]);
        self::assertNotNull($user);

        static::createClient()->request('POST', '/api/auth/password-reset/request', [
            'json' => ['email' => $email],
        ]);

        self::assertResponseStatusCodeSame(202);

        $token = static::getContainer()->get(PasswordResetTokenRepository::class)->findOneBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
        );

        self::assertInstanceOf(PasswordResetToken::class, $token);
        self::assertTrue($token->isUsable(new \DateTimeImmutable()));
    }

    public function testInvalidResetTokenIsRejected(): void
    {
        static::createClient()->request('POST', '/api/auth/password-reset/reset', [
            'json' => [
                'token' => 'not-a-valid-reset-token',
                'password' => 'new-password-123',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}
