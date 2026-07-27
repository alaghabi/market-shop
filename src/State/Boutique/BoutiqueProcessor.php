<?php

namespace App\State\Boutique;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Boutique\BoutiqueInput;
use App\Dto\Boutique\BoutiqueOutput;
use App\Entity\Boutique;
use App\Entity\BoutiqueSettings;
use App\Enum\BoutiqueStatus;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use App\Service\Boutique\ReservedSlugRegistry;
use App\Service\Notification\BackofficeNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class BoutiqueProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly BoutiqueRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly BoutiqueContext $context,
        private readonly BackofficeNotificationService $notifications,
        private readonly ReservedSlugRegistry $reservedSlugs,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BoutiqueOutput
    {
        $isSuperAdmin = $this->context->isSuperAdmin();
        $operationName = $operation->getName() ?? '';

        if (isset($uriVariables['id']) && in_array($operationName, ['approve_boutique', 'reject_boutique', 'suspend_boutique', 'activate_boutique', 'archive_boutique', 'publish_boutique', 'unpublish_boutique'], true)) {
            $entity = $this->findBoutique((string) $uriVariables['id']);

            match ($operationName) {
                'approve_boutique' => $this->approveBoutique($entity),
                'reject_boutique' => $this->rejectBoutique($entity),
                'suspend_boutique' => $this->suspendBoutique($entity),
                'activate_boutique' => $this->activateBoutique($entity),
                'archive_boutique' => $this->archiveBoutique($entity),
                'publish_boutique' => $this->publishBoutique($entity),
                'unpublish_boutique' => $this->unpublishBoutique($entity),
                default => throw new \InvalidArgumentException('Unknown operation: '.$operationName),
            };

            $this->em->flush();

            return $this->toOutput($entity);
        }

        if (!$data instanceof BoutiqueInput) {
            throw new \InvalidArgumentException('Expected BoutiqueInput');
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->findBoutique((string) $uriVariables['id']);

            if (!$this->context->canAccessBoutique($entity)) {
                throw new AccessDeniedHttpException('Access denied');
            }

            $oldSlug = $entity->getSlug();
            $oldStatus = $entity->getStatus();

            $this->validateSlug($data->slug, (string) $entity->getId());
            $this->applyInput($entity, $data);

            if ($oldSlug !== $entity->getSlug() || $oldStatus !== $entity->getStatus()) {
                $this->invalidateCache($oldSlug, $entity->getSlug());
            }
        } else {
            $this->validateSlug($data->slug);
            $entity = new Boutique($data->name, $data->slug);
            $this->applyInput($entity, $data);
            $entity->setStatus($isSuperAdmin ? BoutiqueStatus::Active : BoutiqueStatus::Pending);
            $this->em->persist($entity);
            $this->notifications->notify(null, 'boutique_created', 'Nouvelle boutique', sprintf('La boutique "%s" a été créée avec le statut %s.', $entity->getName(), $entity->getStatus()->value), $entity);
        }

        $this->em->flush();

        return $this->toOutput($entity);
    }

    private function approveBoutique(Boutique $entity): void
    {
        $entity->approve($this->context->getUserIdentifier());

        foreach ($entity->getUserShops() as $userShop) {
            $userShop->setStatus(\App\Enum\UserStatus::Active);
        }

        $this->notifyAdmins($entity, 'boutique.approved', 'Boutique approuvée', sprintf('La boutique "%s" a été approuvée.', $entity->getName()));
    }

    private function rejectBoutique(Boutique $entity): void
    {
        $entity->reject();

        foreach ($entity->getUserShops() as $userShop) {
            $userShop->setStatus(\App\Enum\UserStatus::Rejected);
        }

        $this->notifyAdmins($entity, 'boutique.rejected', 'Boutique rejetée', sprintf('La boutique "%s" a été rejetée.', $entity->getName()));
    }

    private function suspendBoutique(Boutique $entity): void
    {
        $entity->suspend();

        $this->notifyAdmins($entity, 'boutique.suspended', 'Boutique suspendue', sprintf('La boutique "%s" a été suspendue.', $entity->getName()));
    }

    private function activateBoutique(Boutique $entity): void
    {
        $entity->reactivate();

        $this->notifyAdmins($entity, 'boutique.activated', 'Boutique réactivée', sprintf('La boutique "%s" a été réactivée.', $entity->getName()));
    }

    private function archiveBoutique(Boutique $entity): void
    {
        $entity->archive();

        $this->notifyAdmins($entity, 'boutique.archived', 'Boutique archivée', sprintf('La boutique "%s" a été archivée.', $entity->getName()));
    }

    private function publishBoutique(Boutique $entity): void
    {
        $entity->publish();
        $this->notifyAdmins($entity, 'boutique.published', 'Boutique publiée', sprintf('La boutique "%s" est maintenant publique.', $entity->getName()));
    }

    private function unpublishBoutique(Boutique $entity): void
    {
        $entity->unpublish();
        $this->notifyAdmins($entity, 'boutique.unpublished', 'Boutique dépubliée', sprintf('La boutique "%s" n’est plus publique.', $entity->getName()));
    }

    private function notifyAdmins(Boutique $boutique, string $eventCode, string $title, string $message): void
    {
        $this->notifications->notifyBoutiqueAdmins(
            $boutique,
            str_replace('.', '_', $eventCode),
            $title,
            $message,
            $eventCode,
        );
    }

    private function applyInput(Boutique $entity, BoutiqueInput $input): void
    {
        $entity->setName($input->name);
        $entity->setSlug($input->slug);
        $entity->setDescription($input->description);
        $entity->setCoverImage($input->coverImage);
        $entity->setEmail($input->email);
        $entity->setPhone($input->phone);
        $entity->setWebsite($input->website);
        $entity->setCustomDomain($input->customDomain);

        if ($this->context->isSuperAdmin()) {
            $entity->setStatus(BoutiqueStatus::from($input->status));
            $entity->setIsVerified($input->isVerified);
            $entity->setIsFeatured($input->isFeatured);
        }

        $settings = $entity->getSettings() ?? new BoutiqueSettings($entity);
        $settings->updateContact(
            $input->logoUrl,
            $input->primaryColor,
            $input->secondaryColor,
            $input->domain,
            $input->contactEmail,
            $input->contactPhone,
            $input->address,
            $input->socialLinks,
        );
        $settings->setMetaPixelId($input->metaPixelId);
        $this->em->persist($settings);
    }

    private function toOutput(Boutique $entity): BoutiqueOutput
    {
        $output = new BoutiqueOutput();
        $output->id = (string) $entity->getId();
        $output->name = $entity->getName();
        $output->slug = $entity->getSlug();
        $output->status = $entity->getStatus()->value;
        $output->ownerId = $entity->getOwner() ? (string) $entity->getOwner()->getId() : null;
        $output->description = $entity->getDescription();
        $output->coverImage = $entity->getCoverImage();
        $output->email = $entity->getEmail();
        $output->phone = $entity->getPhone();
        $output->website = $entity->getWebsite();
        $output->customDomain = $entity->getCustomDomain();
        $output->isVerified = $entity->isVerified();
        $output->isFeatured = $entity->isFeatured();
        $output->isPublished = $entity->isPublished();
        $output->approvedAt = $entity->getApprovedAt()?->format('c');
        $output->approvedBy = $entity->getApprovedBy();
        $output->rejectionReason = $entity->getRejectionReason();
        $settings = $entity->getSettings();
        $output->primaryColor = $settings?->getPrimaryColor() ?? '#3525cd';
        $output->secondaryColor = $settings?->getSecondaryColor() ?? '#505f76';
        $output->domain = $settings?->getDomain();
        $output->logoUrl = $settings?->getLogoUrl();
        $output->contactEmail = $settings?->getContactEmail();
        $output->contactPhone = $settings?->getContactPhone();
        $output->address = $settings?->getAddress();
        $output->socialLinks = $settings?->getSocialLinks() ?? [];
        $output->metaPixelId = $settings?->getMetaPixelId();
        $output->createdAt = $entity->getCreatedAt();
        $output->updatedAt = $entity->getUpdatedAt();
        $output->usersCount = $entity->getUsers()->count();
        $output->productsCount = $entity->getProducts()->count();
        $output->ordersCount = $entity->getOrdersCount();
        $output->totalRevenue = $entity->getTotalRevenue();
        $output->hasActiveSubscription = $entity->hasActiveSubscription();
        $output->isVisiblePublicly = $entity->isVisiblePublicly();
        $output->subdomainUrl = $entity->getSubdomainUrl();

        return $output;
    }

    private function validateSlug(string $slug, ?string $excludeId = null): void
    {
        if ($this->reservedSlugs->isReserved($slug)) {
            throw new BadRequestHttpException(sprintf('Le slug "%s" est réservé et ne peut pas être utilisé.', $slug));
        }

        $existing = $this->repository->findBySlug($slug);
        if (null !== $existing && (null === $excludeId || (string) $existing->getId() !== $excludeId)) {
            throw new ConflictHttpException(sprintf('Le slug "%s" est déjà utilisé par une autre boutique.', $slug));
        }
    }

    private function invalidateCache(string ...$slugs): void
    {
        // Cache invalidation is handled via BoutiqueCacheSubscriber (Doctrine postUpdate/postRemove)
    }

    private function findBoutique(string $id): Boutique
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Boutique not found');
        }

        return $entity;
    }
}
