<?php

namespace App\Tests\Service\Boutique;

use App\Repository\BoutiqueRepository;
use App\Service\Boutique\SubdomainResolver;
use PHPUnit\Framework\TestCase;

final class SubdomainResolverTest extends TestCase
{
    public function testExtractSubdomainNormalizesHostAndRejectsExcludedHosts(): void
    {
        $resolver = new SubdomainResolver(
            (new \ReflectionClass(BoutiqueRepository::class))->newInstanceWithoutConstructor(),
            'hanooti.com',
        );

        self::assertSame('my-shop', $resolver->extractSubdomain('MY-SHOP.hanooti.com.'));
        self::assertNull($resolver->extractSubdomain('www.hanooti.com'));
        self::assertNull($resolver->extractSubdomain('auth.hanooti.com'));
        self::assertNull($resolver->extractSubdomain('hanooti.com'));
        self::assertNull($resolver->extractSubdomain('my-shop.example.com'));
    }
}
