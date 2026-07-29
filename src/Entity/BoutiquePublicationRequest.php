<?php

namespace App\Entity;

use App\Enum\Subscription\SubscriptionRequestStatus;
use App\Repository\BoutiquePublicationRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoutiquePublicationRequestRepository::class)]
#[ORM\Table(name: 'boutique_publication_request')]
#[ORM\Index(columns: ['boutique_id', 'status'], name: 'idx_publication_request_boutique_status')]
class BoutiquePublicationRequest extends AbstractEntity
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Boutique::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Boutique $boutique,
        #[ORM\Column(length: 16, enumType: SubscriptionRequestStatus::class)]
        private SubscriptionRequestStatus $status = SubscriptionRequestStatus::Pending,
        ?\DateTimeImmutable $requestedAt = null,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $handledAt = null,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $handledBy = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $reason = null,
    ) {
        parent::__construct();
        $this->requestedAt = $requestedAt ?? new \DateTimeImmutable();
    }

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    public function getBoutique(): Boutique
    {
        return $this->boutique;
    }

    public function getStatus(): SubscriptionRequestStatus
    {
        return $this->status;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getHandledAt(): ?\DateTimeImmutable
    {
        return $this->handledAt;
    }

    public function getHandledBy(): ?string
    {
        return $this->handledBy;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function approve(string $handledBy): void
    {
        $this->status = SubscriptionRequestStatus::Approved;
        $this->handledBy = $handledBy;
        $this->handledAt = new \DateTimeImmutable();
    }

    public function reject(string $handledBy, string $reason): void
    {
        $this->status = SubscriptionRequestStatus::Rejected;
        $this->handledBy = $handledBy;
        $this->handledAt = new \DateTimeImmutable();
        $this->reason = $reason;
    }
}
