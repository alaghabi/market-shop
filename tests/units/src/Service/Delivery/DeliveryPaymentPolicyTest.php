<?php

namespace App\Tests\Service\Delivery;

use App\Entity\Boutique;
use App\Entity\Order;
use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Service\Delivery\DeliveryPaymentPolicy;
use PHPUnit\Framework\TestCase;

final class DeliveryPaymentPolicyTest extends TestCase
{
    public function testActiveCashOnDeliveryCanBeSubmittedBeforePayment(): void
    {
        $boutique = new Boutique('Demo', 'demo');
        $order = new Order($boutique, null, OrderChannel::Online, OrderStatus::Pending, 2500, 0, 2500, 'TND');
        $order->setPaymentMethodCode('CASH_ON_DELIVERY');

        $policy = new DeliveryPaymentPolicy();

        self::assertTrue($policy->canSubmit($order));
        self::assertSame(2500, $policy->codAmountCents($order));
    }

    public function testCashOnDeliveryDoesNotRequirePaidPayment(): void
    {
        $boutique = new Boutique('Demo', 'demo');
        $order = new Order($boutique, null, OrderChannel::Online, OrderStatus::Pending, 2500, 0, 2500, 'TND');
        $order->setPaymentMethodCode('CASH_ON_DELIVERY');

        $policy = new DeliveryPaymentPolicy();

        self::assertTrue($policy->canSubmit($order));
        self::assertSame(2500, $policy->codAmountCents($order));
    }

    public function testOnlineOrderRequiresPaidPayment(): void
    {
        $boutique = new Boutique('Demo', 'demo');
        $order = new Order($boutique, null, OrderChannel::Online, OrderStatus::Paid, 2500, 0, 2500, 'TND');
        $order->setPaymentMethodCode('CARD_PAYMENT');

        $policy = new DeliveryPaymentPolicy();

        self::assertFalse($policy->canSubmit($order));
        $order->setPaymentStatus(PaymentStatus::Paid);

        self::assertTrue($policy->canSubmit($order));
        self::assertSame(0, $policy->codAmountCents($order));
    }
}
