<?php

namespace App\Service\Chat;

use App\Entity\Boutique;
use App\Entity\ChatbotConfig;
use App\Enum\ChatbotMode;
use App\Service\Module\ModuleAccessService;

final readonly class ChatbotCapabilityService
{
    public function __construct(
        private ModuleAccessService $moduleAccess,
    ) {
    }

    public function isAiAllowed(Boutique $boutique): bool
    {
        return $this->moduleAccess->isModuleEnabled('chatbot', $boutique);
    }

    public function isVisible(Boutique $boutique, ?ChatbotConfig $config): bool
    {
        if (null === $config || !$config->isEnabled()) {
            return false;
        }

        if (ChatbotMode::Manual === $config->getMode()) {
            return true;
        }

        return $this->isAiAllowed($boutique);
    }

    public function canDispatchAi(Boutique $boutique, ?ChatbotConfig $config): bool
    {
        return null !== $config
            && $config->isEnabled()
            && ChatbotMode::Ai === $config->getMode()
            && $this->isAiAllowed($boutique);
    }
}
