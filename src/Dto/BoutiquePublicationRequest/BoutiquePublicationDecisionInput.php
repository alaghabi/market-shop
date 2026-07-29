<?php

namespace App\Dto\BoutiquePublicationRequest;

use Symfony\Component\Validator\Constraints as Assert;

final class BoutiquePublicationDecisionInput
{
    #[Assert\Length(max: 2000)]
    public ?string $reason = null;
}
