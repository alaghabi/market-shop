<?php

namespace App\Entity;

use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'customer_order')]
#[ORM\Index(name: 'idx_order_delivery', columns: ['delivery_status'])]
#[ORM\Index(name: 'idx_order_delivery_submit', columns: ['submitted_to_delivery', 'delivery_retry_count'])]
class Order extends AbstractEntity
{
    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Boutique::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Boutique $boutique,
        #[ORM\ManyToOne(targetEntity: Customer::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Customer $customer,
        #[ORM\Column(length: 32, enumType: OrderChannel::class)]
        private OrderChannel $channel = OrderChannel::Online,
        #[ORM\Column(length: 32, enumType: OrderStatus::class)]
        private OrderStatus $status = OrderStatus::Draft,
        #[ORM\Column]
        private int $subtotalCents = 0,
        #[ORM\Column]
        private int $discountCents = 0,
        #[ORM\Column]
        private int $totalCents = 0,
        #[ORM\Column(length: 3)]
        private string $currency = 'TND',
        #[ORM\Column]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column(length: 240, nullable: true)]
        private ?string $customerName = null,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $customerEmail = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $customerPhone = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $shippingAddress = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $shippingCity = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $shippingPostalCode = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $shippingCountry = null,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?string $shippingCountryId = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $shippingGovernorate = null,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?string $shippingGovernorateId = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $shippingLocality = null,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?string $shippingLocalityId = null,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $deliveryStatus = null,
        #[ORM\Column(length: 32, enumType: PaymentStatus::class)]
        private PaymentStatus $paymentStatus = PaymentStatus::Pending,
        #[ORM\Column(length: 80, nullable: true)]
        private ?string $paymentMethodCode = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $deliveryTracking = null,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $deliveredAt = null,
        #[ORM\Column]
        private bool $submittedToDelivery = false,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $submittedAt = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $deliveryError = null,
        #[ORM\Column]
        private int $deliveryRetryCount = 0,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $lastRetryAt = null,
        #[ORM\ManyToOne(targetEntity: BoutiqueDeliveryAccount::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?BoutiqueDeliveryAccount $deliveryAccount = null,
    ) {
        parent::__construct();
        $this->items = new ArrayCollection();
    }

    public function setCustomerSnapshot(
        ?string $customerName,
        ?string $customerEmail,
        ?string $customerPhone,
        ?string $shippingAddress,
        ?string $shippingCity,
        ?string $shippingPostalCode = null,
        ?string $shippingCountry = null,
        ?string $shippingCountryId = null,
        ?string $shippingGovernorate = null,
        ?string $shippingGovernorateId = null,
        ?string $shippingLocality = null,
        ?string $shippingLocalityId = null,
    ): void {
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
        $this->customerPhone = $customerPhone;
        $this->shippingAddress = $shippingAddress;
        $this->shippingCity = $shippingCity;
        $this->shippingPostalCode = $shippingPostalCode;
        $this->shippingCountry = $shippingCountry;
        $this->shippingCountryId = $shippingCountryId;
        $this->shippingGovernorate = $shippingGovernorate;
        $this->shippingGovernorateId = $shippingGovernorateId;
        $this->shippingLocality = $shippingLocality;
        $this->shippingLocalityId = $shippingLocalityId;
    }

    public function addItem(?Product $product, string $productName, string $sku, int $quantity, int $unitPriceCents, ?ProductVariant $variant = null): void
    {
        $item = new OrderItem(
            $this,
            $product,
            $productName,
            $sku,
            $quantity,
            $unitPriceCents,
            0,
            $quantity * $unitPriceCents,
            $variant,
        );

        $this->items->add($item);
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getBoutique(): Boutique
    {
        return $this->boutique;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function getChannel(): OrderChannel
    {
        return $this->channel;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getSubtotalCents(): int
    {
        return $this->subtotalCents;
    }

    public function getDiscountCents(): int
    {
        return $this->discountCents;
    }

    public function setDiscountCents(int $discountCents): void
    {
        $this->discountCents = $discountCents;
    }

    public function getTotalCents(): int
    {
        return $this->totalCents;
    }

    public function setTotalCents(int $totalCents): void
    {
        $this->totalCents = $totalCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function getShippingAddress(): ?string
    {
        return $this->shippingAddress;
    }

    public function getShippingCity(): ?string
    {
        return $this->shippingCity;
    }

    public function getShippingPostalCode(): ?string
    {
        return $this->shippingPostalCode;
    }

    public function getShippingCountry(): ?string
    {
        return $this->shippingCountry;
    }

    public function getShippingCountryId(): ?string
    {
        return $this->shippingCountryId;
    }

    public function getShippingGovernorate(): ?string
    {
        return $this->shippingGovernorate;
    }

    public function getShippingGovernorateId(): ?string
    {
        return $this->shippingGovernorateId;
    }

    public function getShippingLocality(): ?string
    {
        return $this->shippingLocality;
    }

    public function getShippingLocalityId(): ?string
    {
        return $this->shippingLocalityId;
    }

    public function getDeliveryStatus(): ?string
    {
        return $this->deliveryStatus;
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(PaymentStatus $paymentStatus): void
    {
        $this->paymentStatus = $paymentStatus;
    }

    public function getPaymentMethodCode(): ?string
    {
        return $this->paymentMethodCode;
    }

    public function setPaymentMethodCode(?string $paymentMethodCode): void
    {
        $this->paymentMethodCode = $paymentMethodCode;
    }

    public function getDeliveryTracking(): ?string
    {
        return $this->deliveryTracking;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function isSubmittedToDelivery(): bool
    {
        return $this->submittedToDelivery;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getDeliveryError(): ?string
    {
        return $this->deliveryError;
    }

    public function getDeliveryRetryCount(): int
    {
        return $this->deliveryRetryCount;
    }

    public function getLastRetryAt(): ?\DateTimeImmutable
    {
        return $this->lastRetryAt;
    }

    public function getDeliveryAccount(): ?BoutiqueDeliveryAccount
    {
        return $this->deliveryAccount;
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
    }

    public function markAsShipped(string $tracking): void
    {
        $this->status = OrderStatus::Shipped;
        $this->deliveryStatus = 'shipped';
        $this->deliveryTracking = $tracking;
    }

    public function markAsDelivered(): void
    {
        $this->status = OrderStatus::Delivered;
        $this->deliveryStatus = 'delivered';
        $this->deliveredAt = new \DateTimeImmutable();
    }

    public function markDeliverySubmitted(): void
    {
        $this->submittedToDelivery = true;
        $this->submittedAt = new \DateTimeImmutable();
        $this->deliveryError = null;
    }

    public function markDeliveryError(string $error): void
    {
        $this->deliveryError = $error;
        $this->deliveryStatus = 'error';
        ++$this->deliveryRetryCount;
        $this->lastRetryAt = new \DateTimeImmutable();
    }

    public function setDeliveryAccount(BoutiqueDeliveryAccount $account): void
    {
        $this->deliveryAccount = $account;
    }
}
