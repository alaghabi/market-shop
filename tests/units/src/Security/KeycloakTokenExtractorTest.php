<?php

namespace App\Tests\Security;

use App\Security\KeycloakTokenExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class KeycloakTokenExtractorTest extends TestCase
{
    public function testItExtractsAnRs256BearerToken(): void
    {
        $token = $this->token('{"alg":"RS256","typ":"JWT"}');
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertSame($token, (new KeycloakTokenExtractor())->extractAccessToken($request));
    }

    public function testItDoesNotExtractLocalHs256Tokens(): void
    {
        $token = $this->token('{"alg":"HS256","typ":"JWT"}');
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertNull((new KeycloakTokenExtractor())->extractAccessToken($request));
    }

    public function testItDoesNotExtractMalformedTokens(): void
    {
        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt']);

        self::assertNull((new KeycloakTokenExtractor())->extractAccessToken($request));
    }

    private function token(string $header): string
    {
        return rtrim(strtr(base64_encode($header), '+/', '-_'), '=').'.payload.signature';
    }
}
