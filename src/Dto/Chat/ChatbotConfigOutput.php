<?php

namespace App\Dto\Chat;

final class ChatbotConfigOutput
{
    public ?string $id = null;
    public ?string $boutiqueId = null;
    public string $mode = 'MANUAL';
    public string $model = 'llama3.2:1b';
    public ?string $systemPrompt = null;
    public float $temperature = 0.7;
    public int $maxTokens = 512;
    public bool $isEnabled = false;
    public bool $chatbotEnabled = false;
    public bool $aiAllowed = false;
}
