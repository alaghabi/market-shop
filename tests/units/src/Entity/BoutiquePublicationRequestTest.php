<?php

namespace App\Tests\Entity;

use App\Entity\Boutique;
use App\Entity\BoutiquePublicationRequest;
use App\Enum\Subscription\SubscriptionRequestStatus;
use PHPUnit\Framework\TestCase;

final class BoutiquePublicationRequestTest extends TestCase
{
    public function testApprovalAndRejectionRecordTheDecision(): void
    {
        $boutique = new Boutique('Demo', 'demo');
        $request = new BoutiquePublicationRequest($boutique);

        $request->approve('admin@example.test');
        self::assertSame(SubscriptionRequestStatus::Approved, $request->getStatus());
        self::assertSame('admin@example.test', $request->getHandledBy());

        $rejected = new BoutiquePublicationRequest($boutique);
        $rejected->reject('admin@example.test', 'Catalogue incomplet');
        self::assertSame(SubscriptionRequestStatus::Rejected, $rejected->getStatus());
        self::assertSame('Catalogue incomplet', $rejected->getReason());
    }
}
