<?php

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\EmailVerificationToken;
use App\Repository\EmailVerificationTokenRepository;
use App\Repository\UserRepository;

final class EmailVerificationApiTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testBoutiqueRegistrationRequiresEmailVerification(): void
    {
        $suffix = strtolower(bin2hex(random_bytes(4)));
        $email = 'verification-'.$suffix.'@example.test';

        $client = static::createClient();
        $client->request('POST', '/api/auth/register', [
            'json' => [
                'email' => $email,
                'password' => 'Password123!',
                'displayName' => 'Verification Admin',
                'boutiqueName' => 'Verification Boutique '.$suffix,
                'boutiqueSlug' => 'verification-'.$suffix,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['verificationRequired' => true]);
        self::assertArrayNotHasKey('accessToken', $client->getResponse()->toArray(false));

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['identifier' => $email]);
        self::assertNotNull($user);
        self::assertFalse($user->isEmailVerified());
        self::assertInstanceOf(
            EmailVerificationToken::class,
            static::getContainer()->get(EmailVerificationTokenRepository::class)->findOneBy(['user' => $user]),
        );

        $client->request('POST', '/api/auth/login', [
            'json' => ['email' => $email, 'password' => 'Password123!'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
