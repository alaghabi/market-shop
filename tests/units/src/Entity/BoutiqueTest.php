<?php

namespace App\Tests\Entity;

use App\Entity\Boutique;
use PHPUnit\Framework\TestCase;

final class BoutiqueTest extends TestCase
{
    public function testSubdomainUrlUsesTheConfiguredRootDomain(): void
    {
        $boutique = new Boutique('Demo', 'demo-shop');

        self::assertSame('https://demo-shop.hanooti.shop', $boutique->getSubdomainUrl('hanooti.shop'));
    }
}
