<?php

namespace App\Dto\AccountSubscription;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminAccountSubscriptionInput
{
    #[Assert\NotBlank(message: 'L\'utilisateur est obligatoire.')]
    #[Assert\Uuid(message: 'Identifiant d\'utilisateur invalide.')]
    public ?string $userId = null;

    #[Assert\NotBlank(message: 'Le plan est obligatoire.')]
    #[Assert\Uuid(message: 'Identifiant de plan invalide.')]
    public ?string $planId = null;

    /** @var list<string> */
    #[Assert\All([new Assert\Uuid(message: 'Identifiant d\'extension invalide.')])]
    public array $extensionIds = [];

    #[Assert\DateTime(message: 'Date de début invalide.')]
    public ?string $startDate = null;

    #[Assert\DateTime(message: 'Date de fin invalide.')]
    public ?string $endDate = null;
}
