<?php

namespace App\Dto\AccountSubscription;

use Symfony\Component\Validator\Constraints as Assert;

final class AccountSubscriptionInput
{
    #[Assert\NotBlank(message: 'Le plan est obligatoire.')]
    public ?string $planId = null;

    /** @var list<string> */
    #[Assert\All([new Assert\Uuid(message: 'Identifiant d\'extension invalide.')])]
    public array $extensionIds = [];
}
