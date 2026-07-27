<?php

namespace App\Entity;

use App\Enum\CartStatus;
use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\Table(name: 'cart')]
#[ORM\Index(name: 'idx_cart_boutique_status', columns: ['boutique_id', 'status'])]
class Cart extends AbstractEntity
{
    /** @var Collection<int, CartItem> */
    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Boutique::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Boutique $boutique,
        #[ORM\Column(length: 80)]
        private string $sessionToken,
        #[ORM\ManyToOne(targetEntity: Customer::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Customer $customer = null,
        #[ORM\Column(length: 32, enumType: CartStatus::class)]
        private CartStatus $status = CartStatus::Active,
        #[ORM\Column(length: 3)]
        private string $currency = 'TND',
        #[ORM\Column]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column]
        private \DateTimeImmutable $updatedAt = new \DateTimeImmutable(),
        #[ORM\Column]
        private \DateTimeImmutable $expiresAt = new \DateTimeImmutable('+30 days'),
    ) {
        parent::__construct();
        $this->items = new ArrayCollection();
    }

    public function getBoutique(): Boutique
    {
        return $this->boutique;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
        $this->touch();
    }

    public function getStatus(): CartStatus
    {
        return $this->status;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSessionToken(): string
    {
        return $this->sessionToken;
    }

    /** @return Collection<int, CartItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function findItemForProduct(Product $product, ?ProductVariant $variant = null): ?CartItem
    {
        foreach ($this->items as $item) {
            if ((string) $item->getProduct()?->getId() === (string) $product->getId()
                && (string) $item->getVariant()?->getId() === (string) $variant?->getId()) {
                return $item;
            }
        }

        return null;
    }

    public function addItem(Product $product, int $quantity, ?ProductVariant $variant = null): CartItem
    {
        $item = $this->findItemForProduct($product, $variant);
        if ($item instanceof CartItem) {
            $item->changeQuantity($item->getQuantity() + $quantity);
            $this->touch();

            return $item;
        }

        $item = new CartItem($this, $product, $quantity, $variant?->getSellingPrice() ?? $product->getSellingPrice(), $variant);
        $this->items->add($item);
        $this->touch();

        return $item;
    }

    public function removeItem(CartItem $item): void
    {
        $this->items->removeElement($item);
        $this->touch();
    }

    public function getTotalCents(): int
    {
        return array_reduce(
            $this->items->toArray(),
            static fn (int $total, CartItem $item): int => $total + $item->getTotalCents(),
            0,
        );
    }

    public function markOrdered(): void
    {
        $this->status = CartStatus::Ordered;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
