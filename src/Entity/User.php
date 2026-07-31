<?php

namespace App\Entity;

use App\Doctrine\Traits\SoftDeleteTrait;
use App\Enum\UserStatus;
use App\Entity\Contract\SoftDeletableInterface;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User extends AbstractEntity implements UserInterface, SoftDeletableInterface
{
    use SoftDeleteTrait;

    /**
     * @param list<string>                   $roles
     * @param Collection<int, Boutique>|null $administeredBoutiques
     * @param Collection<int, UserShop>|null $userShops
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Boutique::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Boutique $boutique,
        #[ORM\Column(length: 180, unique: true)]
        private string $identifier,
        #[ORM\Column(type: 'json')]
        private array $roles = ['ROLE_USER'],
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $displayName = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $passwordHash = null,
        #[ORM\ManyToMany(targetEntity: Boutique::class, inversedBy: 'users')]
        #[ORM\JoinTable(name: 'user_administered_boutique')]
        private ?Collection $administeredBoutiques = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $firstname = null,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $lastname = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $phone = null,
        #[ORM\Column(length: 32, enumType: UserStatus::class)]
        private UserStatus $status = UserStatus::Pending,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $lastLoginAt = null,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $emailVerifiedAt = null,
        #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserShop::class, cascade: ['persist'], orphanRemoval: true)]
        private ?Collection $userShops = null,
        #[ORM\OneToMany(mappedBy: 'user', targetEntity: AccountSubscription::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
        private ?Collection $accountSubscriptions = null,
    ) {
        parent::__construct();
        $this->createdAt = new \DateTimeImmutable();
        $this->administeredBoutiques ??= new ArrayCollection();
        $this->userShops ??= new ArrayCollection();
        $this->accountSubscriptions ??= new ArrayCollection();

        if (null !== $boutique) {
            $this->addAdministeredBoutique($boutique);
        }
    }

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $keycloakSubject = null;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getBoutique(): ?Boutique
    {
        return $this->boutique;
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getKeycloakSubject(): ?string
    {
        return $this->keycloakSubject;
    }

    public function setKeycloakSubject(?string $keycloakSubject): void
    {
        $this->keycloakSubject = $keycloakSubject;
        $this->touch();
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setPassword(string $plainPassword): void
    {
        $this->passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $this->touch();
    }

    public function isPasswordValid(string $plainPassword): bool
    {
        return null !== $this->passwordHash && password_verify($plainPassword, $this->passwordHash);
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function eraseCredentials(): void
    {
    }

    /** @return Collection<int, Boutique> */
    public function getAdministeredBoutiques(): Collection
    {
        return $this->administeredBoutiques ?? new ArrayCollection();
    }

    public function addAdministeredBoutique(Boutique $boutique): void
    {
        if (!$this->getAdministeredBoutiques()->contains($boutique)) {
            $this->getAdministeredBoutiques()->add($boutique);
            $this->touch();
        }
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): void
    {
        $this->firstname = $firstname;
        $this->touch();
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): void
    {
        $this->lastname = $lastname;
        $this->touch();
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
        $this->touch();
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function isEmailVerified(): bool
    {
        return null !== $this->emailVerifiedAt;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function markEmailVerified(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function setStatus(UserStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function markLoggedIn(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        $this->touch();
    }

    /** @return Collection<int, UserShop> */
    public function getUserShops(): Collection
    {
        return $this->userShops ?? new ArrayCollection();
    }

    public function addUserShop(UserShop $userShop): void
    {
        if (!$this->getUserShops()->contains($userShop)) {
            $this->getUserShops()->add($userShop);
            $this->touch();
        }
    }

    /** @return Collection<int, AccountSubscription> */
    public function getAccountSubscriptions(): Collection
    {
        return $this->accountSubscriptions ?? new ArrayCollection();
    }

    public function addAccountSubscription(AccountSubscription $accountSubscription): void
    {
        if (!$this->getAccountSubscriptions()->contains($accountSubscription)) {
            $this->getAccountSubscriptions()->add($accountSubscription);
            $this->touch();
        }
    }
}
