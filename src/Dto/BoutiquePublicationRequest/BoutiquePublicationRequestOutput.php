<?php

namespace App\Dto\BoutiquePublicationRequest;

final class BoutiquePublicationRequestOutput
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
    public ?string $boutiqueName = null;
    public string $status = 'pending';
    public ?string $requestedAt = null;
    public ?string $handledAt = null;
    public ?string $handledBy = null;
    public ?string $reason = null;
}
