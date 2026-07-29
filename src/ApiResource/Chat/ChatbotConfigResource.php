<?php

namespace App\ApiResource\Chat;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Chat\ChatbotConfigInput;
use App\Dto\Chat\ChatbotConfigOutput;
use App\State\Chat\ChatbotConfigProcessor;
use App\State\Chat\ChatbotConfigProvider;

#[ApiResource(
    shortName: 'ChatbotConfig',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/chatbot-configs',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: ChatbotConfigOutput::class,
            provider: ChatbotConfigProvider::class,
        ),
        new Post(
            uriTemplate: '/admin/chatbot-configs',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            read: false,
            input: ChatbotConfigInput::class,
            output: ChatbotConfigOutput::class,
            processor: ChatbotConfigProcessor::class,
        ),
        new Get(
            uriTemplate: '/admin/chatbot-configs/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: ChatbotConfigOutput::class,
            provider: ChatbotConfigProvider::class,
        ),
        new Patch(
            uriTemplate: '/admin/chatbot-configs/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: ChatbotConfigInput::class,
            output: ChatbotConfigOutput::class,
            provider: ChatbotConfigProvider::class,
            processor: ChatbotConfigProcessor::class,
        ),
        new Get(
            uriTemplate: '/chatbot',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
            output: ChatbotConfigOutput::class,
            provider: ChatbotConfigProvider::class,
        ),
        new Patch(
            uriTemplate: '/chatbot',
            security: "is_granted('ROLE_BOUTIQUE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
            input: ChatbotConfigInput::class,
            output: ChatbotConfigOutput::class,
            provider: ChatbotConfigProvider::class,
            processor: ChatbotConfigProcessor::class,
        ),
    ],
)]
final class ChatbotConfigResource
{
    public ?string $id = null;
    public string $boutiqueId;
    public string $mode = 'MANUAL';
    public string $model = 'llama3.2:1b';
    public ?string $systemPrompt = null;
    public float $temperature = 0.7;
    public int $maxTokens = 512;
    public bool $isEnabled = false;
}
