<?php

namespace App\State\BoutiquePublicationRequest;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\BoutiquePublicationRequest\BoutiquePublicationDecisionInput;
use App\Dto\BoutiquePublicationRequest\BoutiquePublicationRequestInput;
use App\Dto\BoutiquePublicationRequest\BoutiquePublicationRequestOutput;
use App\Entity\Boutique;
use App\Entity\BoutiquePublicationRequest;
use App\Enum\BoutiqueStatus;
use App\Enum\Subscription\SubscriptionRequestStatus;
use App\Repository\BoutiquePublicationRequestRepository;
use App\Repository\UserRepository;
use App\Security\BoutiqueContext;
use App\Service\Notification\BackofficeNotificationService;
use App\Service\NotificationService;
use App\Enum\NotificationChannel;
use App\State\Common\BoutiqueWriteResolverTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BoutiquePublicationRequestProcessor implements ProcessorInterface
{
    use BoutiqueWriteResolverTrait;

    public function __construct(
        private readonly BoutiquePublicationRequestRepository $repository,
        private readonly \App\Repository\BoutiqueRepository $boutiques,
        private readonly EntityManagerInterface $em,
        private readonly BoutiqueContext $context,
        private readonly BackofficeNotificationService $backofficeNotifications,
        private readonly NotificationService $notifications,
        private readonly UserRepository $users,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BoutiquePublicationRequestOutput
    {
        $operationName = $operation->getName() ?? '';
        if ('approve_boutique_publication_request' === $operationName) {
            return $this->approve((string) ($uriVariables['id'] ?? ''));
        }
        if ('reject_boutique_publication_request' === $operationName) {
            $reason = $data instanceof BoutiquePublicationDecisionInput ? trim((string) ($data->reason ?? '')) : '';
            if ('' === $reason) {
                throw new BadRequestHttpException('Une raison est obligatoire pour refuser une demande.');
            }

            return $this->reject((string) ($uriVariables['id'] ?? ''), $reason);
        }
        if (!$data instanceof BoutiquePublicationRequestInput) {
            throw new \InvalidArgumentException('Expected BoutiquePublicationRequestInput');
        }

        $boutique = $this->resolveBoutiqueForWrite($data, $uriVariables, $context);
        if (!$this->context->getUserIdentifier()) {
            throw new BadRequestHttpException('Utilisateur non authentifié.');
        }
        if ($boutique->isPublished()) {
            throw new BadRequestHttpException('Cette boutique est déjà publiée.');
        }
        if (BoutiqueStatus::Suspended === $boutique->getStatus() || BoutiqueStatus::Rejected === $boutique->getStatus() || BoutiqueStatus::Archived === $boutique->getStatus()) {
            throw new BadRequestHttpException('Cette boutique ne peut pas demander sa publication dans son statut actuel.');
        }

        $currentUser = $this->users->findOneBy(['identifier' => $this->context->getUserIdentifier()]);
        if (!$currentUser instanceof \App\Entity\User || !$currentUser->isEmailVerified()) {
            throw new BadRequestHttpException('Vérifiez votre email avant de demander la publication.');
        }
        if ($this->repository->findPendingByBoutique($boutique) instanceof BoutiquePublicationRequest) {
            throw new BadRequestHttpException('Une demande de publication est déjà en attente.');
        }

        $entity = new BoutiquePublicationRequest($boutique);
        $this->em->persist($entity);
        $this->em->flush();

        $message = sprintf('La demande de publication de la boutique "%s" a été reçue. Elle sera traitée sous 24h.', $boutique->getName());
        $this->backofficeNotifications->notifyBoutiqueAdmins($boutique, 'boutique_publication_requested', 'Demande de publication reçue', $message, 'boutique.publication_requested');
        $this->notifySuperAdmins($boutique, 'boutique_publication_requested', 'Nouvelle demande de publication', $message, 'boutique.publication_requested');

        return $this->toOutput($entity);
    }

    private function approve(string $id): BoutiquePublicationRequestOutput
    {
        $entity = $this->findEntity($id);
        $boutique = $entity->getBoutique();
        $entity->approve($this->context->getUserIdentifier() ?? 'super-admin');
        $boutique->approve($this->context->getUserIdentifier() ?? 'super-admin');
        $boutique->publish();
        $this->em->flush();

        $message = sprintf('Votre boutique "%s" a été publiée et est maintenant visible publiquement.', $boutique->getName());
        $this->backofficeNotifications->notifyBoutiqueAdmins($boutique, 'boutique_publication_approved', 'Boutique publiée', $message, 'boutique.publication_approved');

        return $this->toOutput($entity);
    }

    private function reject(string $id, string $reason): BoutiquePublicationRequestOutput
    {
        $entity = $this->findEntity($id);
        $boutique = $entity->getBoutique();
        $entity->reject($this->context->getUserIdentifier() ?? 'super-admin', $reason);
        $boutique->setRejectionReason($reason);
        $this->em->flush();

        $message = sprintf('La demande de publication de votre boutique "%s" a été refusée. Raison : %s', $boutique->getName(), $reason);
        $this->backofficeNotifications->notifyBoutiqueAdmins($boutique, 'boutique_publication_rejected', 'Demande de publication refusée', $message, 'boutique.publication_rejected', ['reason' => $reason]);

        return $this->toOutput($entity);
    }

    private function notifySuperAdmins(Boutique $boutique, string $type, string $title, string $message, string $eventCode): void
    {
        foreach ($this->users->findByRole('ROLE_SUPER_ADMIN') as $admin) {
            $recipient = $admin->getUserIdentifier();
            $this->notifications->notify($recipient, $type, $title, $message, $boutique);
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $this->notifications->dispatchExternal($boutique, $eventCode, NotificationChannel::Email, $recipient, ['message' => $message]);
            }
        }
        $this->em->flush();
    }

    private function findEntity(string $id): BoutiquePublicationRequest
    {
        $entity = $this->repository->find($id);
        if (!$entity instanceof BoutiquePublicationRequest) {
            throw new NotFoundHttpException('Publication request not found');
        }
        if (SubscriptionRequestStatus::Pending !== $entity->getStatus()) {
            throw new BadRequestHttpException('Cette demande a déjà été traitée.');
        }

        return $entity;
    }

    private function toOutput(BoutiquePublicationRequest $entity): BoutiquePublicationRequestOutput
    {
        $output = new BoutiquePublicationRequestOutput();
        $output->id = (string) $entity->getId();
        $output->boutiqueId = (string) $entity->getBoutique()->getId();
        $output->boutiqueName = $entity->getBoutique()->getName();
        $output->status = $entity->getStatus()->value;
        $output->requestedAt = $entity->getRequestedAt()->format('c');
        $output->handledAt = $entity->getHandledAt()?->format('c');
        $output->handledBy = $entity->getHandledBy();
        $output->reason = $entity->getReason();

        return $output;
    }
}
