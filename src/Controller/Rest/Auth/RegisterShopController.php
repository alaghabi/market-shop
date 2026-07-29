<?php

namespace App\Controller\Rest\Auth;

use App\Dto\Auth\RegisterShopInput;
use App\Entity\Boutique;
use App\Entity\BoutiqueSettings;
use App\Entity\Subscription;
use App\Entity\User;
use App\Entity\UserShop;
use App\Enum\BoutiqueStatus;
use App\Enum\PlanType;
use App\Enum\SubscriptionStatus;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\BoutiqueRepository;
use App\Repository\UserRepository;
use App\Security\LocalTokenManager;
use App\Service\NotificationService;
use App\Service\Auth\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterShopController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private BoutiqueRepository $boutiques,
        private LocalTokenManager $tokens,
        private NotificationService $notifications,
        private EmailVerificationService $emailVerification,
    ) {
    }

    #[Route('/api/auth/register-shop', name: 'api_auth_register_shop', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RegisterShopInput $input): JsonResponse
    {
        $email = strtolower(trim($input->email));

        if ($this->users->findOneBy(['identifier' => $email]) instanceof User) {
            return new JsonResponse(['message' => 'Un compte existe déjà pour cet email.'], JsonResponse::HTTP_CONFLICT);
        }

        $slug = $this->slugify($input->boutiqueName);

        if ($this->boutiques->findOneBy(['slug' => $slug]) instanceof Boutique) {
            return new JsonResponse(['message' => 'Ce nom de boutique est déjà utilisé.'], JsonResponse::HTTP_CONFLICT);
        }

        $uniqueSlug = $slug;
        $counter = 1;
        while ($this->boutiques->findOneBy(['slug' => $uniqueSlug]) instanceof Boutique) {
            $uniqueSlug = $slug.'-'.$counter++;
        }

        $boutique = new Boutique($input->boutiqueName, $uniqueSlug);
        $boutique->setStatus(BoutiqueStatus::Pending);

        $user = new User(
            boutique: $boutique,
            identifier: $email,
            roles: [UserRole::BoutiqueAdmin->value],
            displayName: $input->firstname.' '.$input->lastname,
            firstname: $input->firstname,
            lastname: $input->lastname,
            phone: $input->phone,
            status: UserStatus::Pending,
        );
        $user->setPassword($input->password);

        $boutique->setOwner($user);

        $userShop = new UserShop(
            user: $user,
            boutique: $boutique,
            role: UserRole::BoutiqueAdmin->value,
            status: UserStatus::Pending,
        );

        $settings = new BoutiqueSettings($boutique);

        $subscription = new Subscription(
            boutique: $boutique,
            plan: PlanType::Free,
            status: SubscriptionStatus::Pending,
        );
        $boutique->setCurrentSubscription($subscription);

        $this->entityManager->persist($boutique);
        $this->entityManager->persist($user);
        $this->entityManager->persist($userShop);
        $this->entityManager->persist($settings);
        $this->entityManager->persist($subscription);

        $this->notifications->notify(
            null,
            'boutique_created',
            'Nouvelle boutique en attente',
            sprintf('La boutique "%s" a été créée par %s %s et attend une validation Super Admin.', $boutique->getName(), $input->firstname, $input->lastname),
            $boutique,
        );
        $this->notifications->notify(
            $email,
            'boutique_pending',
            'Boutique en attente de validation',
            sprintf('Votre boutique "%s" a été enregistrée. Vous recevrez une notification après validation.', $boutique->getName()),
            $boutique,
        );

        $this->entityManager->flush();

        $this->emailVerification->issue($user, $boutique);

        return new JsonResponse([
            'message' => 'Boutique enregistrée. Vérifiez votre email pour activer votre accès. La publication sera demandée depuis le back-office.',
            'verificationRequired' => true,
            'boutique' => [
                'id' => (string) $boutique->getId(),
                'name' => $boutique->getName(),
                'slug' => $boutique->getSlug(),
                'status' => $boutique->getStatus()->value,
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
