<?php

namespace App\Entity;

use App\Enum\ChatbotMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chatbot_config')]
class ChatbotConfig extends AbstractEntity
{
    #[ORM\OneToOne(targetEntity: Boutique::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Boutique $boutique;

    #[ORM\Column(length: 64, options: ['default' => 'llama3.2:1b'])]
    private string $model = 'llama3.2:1b';

    #[ORM\Column(length: 16, enumType: ChatbotMode::class, options: ['default' => 'MANUAL'])]
    private ChatbotMode $mode = ChatbotMode::Manual;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $systemPrompt = null;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.7])]
    private float $temperature = 0.7;

    #[ORM\Column(options: ['default' => 512])]
    private int $maxTokens = 512;

    #[ORM\Column(options: ['default' => false])]
    private bool $isEnabled = false;

    public function __construct(Boutique $boutique)
    {
        parent::__construct();
        $this->boutique = $boutique;
    }

    public function getBoutique(): Boutique
    {
        return $this->boutique;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getMode(): ChatbotMode
    {
        return $this->mode;
    }

    public function setMode(ChatbotMode $mode): void
    {
        $this->mode = $mode;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function setSystemPrompt(?string $systemPrompt): void
    {
        $this->systemPrompt = $systemPrompt;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): void
    {
        $this->temperature = $temperature;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->maxTokens = $maxTokens;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): void
    {
        $this->isEnabled = $isEnabled;
    }
}
