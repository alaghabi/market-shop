<?php

namespace App\Dto\Boutique;

use Symfony\Component\Validator\Constraints as Assert;

final class BoutiqueActionInput
{
    #[Assert\Length(max: 2000)]
    public ?string $reason = null;
}
