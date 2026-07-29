<?php

namespace App\Dto\BoutiquePublicationRequest;

use Symfony\Component\Validator\Constraints as Assert;

final class BoutiquePublicationRequestInput
{
    public ?string $boutiqueId = null;

    #[Assert\Length(max: 2000)]
    public ?string $reason = null;
}
