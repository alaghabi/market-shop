<?php

namespace App\Entity;

use App\Enum\NotificationChannel;
use App\Enum\NotificationLogStatus;
use App\Repository\NotificationLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationLogRepository::class)]
#[ORM\Table(name: 'notification_log')]
class NotificationLog extends AbstractEntity
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Boutique::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Boutique $boutique,
        #[ORM\Column(length: 16, enumType: NotificationChannel::class)]
        private NotificationChannel $channel,
        #[ORM\Column(length: 180)]
        private string $recipient,
        #[ORM\Column(length: 80)]
        private string $eventCode,
        #[ORM\Column(length: 16, enumType: NotificationLogStatus::class)]
        private NotificationLogStatus $status,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $sentAt = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $errorMessage = null,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column(length: 160, nullable: true, unique: true)]
        private ?string $deduplicationKey = null,
    ) {
        parent::__construct();
    }

    public function markSent(): void
    {
        $this->status = NotificationLogStatus::Sent;
        $this->sentAt = new \DateTimeImmutable();
        $this->errorMessage = null;
    }

    public function markFailed(?string $errorMessage): void
    {
        $this->status = NotificationLogStatus::Failed;
        $this->errorMessage = $errorMessage;
    }

    public function getBoutique(): ?Boutique
    {
        return $this->boutique;
    }

    public function getChannel(): NotificationChannel
    {
        return $this->channel;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getEventCode(): string
    {
        return $this->eventCode;
    }

    public function getStatus(): NotificationLogStatus
    {
        return $this->status;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeduplicationKey(): ?string
    {
        return $this->deduplicationKey;
    }
}
