<?php

namespace App\Service\Delivery;

use App\Entity\Order;
use App\Enum\PaymentMethodType;
use App\Enum\PaymentStatus;

final class DeliveryPaymentPolicy
{
    public function canSubmit(Order $order): bool
    {
        return $this->isCashOnDelivery($order)
            || PaymentStatus::Paid === $order->getPaymentStatus();
    }

    public function isCashOnDelivery(Order $order): bool
    {
        return PaymentMethodType::CashOnDelivery->value === strtoupper((string) $order->getPaymentMethodCode());
    }

    public function codAmountCents(Order $order): int
    {
        return $this->isCashOnDelivery($order) ? $order->getTotalCents() : 0;
    }
}
