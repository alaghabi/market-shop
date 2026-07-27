<?php

namespace App\Dto\Order;

final class OrderOutput
{
    public string $id;
    public string $boutiqueId;
    public string $customerId;
    public string $customerName;
    public string $customerEmail;
    public ?string $customerPhone = null;
    public string $channel;
    public string $status;
    public int $subtotalCents;
    public int $discountCents;
    public int $totalCents;
    public string $currency;
    /** @var list<array<string, mixed>> */
    public array $items;
    public ?string $shippingAddress = null;
    public ?string $shippingCity = null;
    public ?string $shippingPostalCode = null;
    public ?string $shippingCountry = null;
    public ?string $shippingGovernorate = null;
    public ?string $shippingLocality = null;
    public ?string $deliveryStatus = null;
    public string $paymentStatus = 'pending';
    public ?string $paymentMethodCode = null;
    public ?string $deliveryTracking = null;
    public ?string $deliveredAt = null;
    public \DateTimeImmutable $createdAt;
    public \DateTimeImmutable $updatedAt;
}
