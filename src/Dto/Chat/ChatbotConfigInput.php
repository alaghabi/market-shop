<?php

namespace App\Dto\Chat;

final class ChatbotConfigInput
{
    public ?string $boutiqueId = null;
    public ?string $mode = null;
    public ?string $model = null;
    public ?string $systemPrompt = null;
    public ?float $temperature = null;
    public ?int $maxTokens = null;
    public ?bool $isEnabled = null;
}
