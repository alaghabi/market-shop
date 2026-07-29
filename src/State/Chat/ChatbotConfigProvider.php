<?php

namespace App\State\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Chat\ChatbotConfigOutput;
use App\Entity\ChatbotConfig;
use App\Repository\BoutiqueRepository;
use App\Repository\ChatbotConfigRepository;
use App\Security\BoutiqueContext;
use App\Service\Chat\ChatbotCapabilityService;
use App\State\Common\BoutiqueAwareProviderTrait;

/** @implements ProviderInterface<ChatbotConfigOutput> */
final readonly class ChatbotConfigProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private ChatbotConfigRepository $configs,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private ChatbotCapabilityService $capability,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|ChatbotConfigOutput|null
    {
        if (isset($uriVariables['id'])) {
            $config = $this->configs->find($uriVariables['id']);

            return $config instanceof ChatbotConfig ? $this->toOutput($config) : null;
        }

        if ($this->context->isSuperAdmin() && '/admin/chatbot-configs' === $operation->getUriTemplate()) {
            return array_map($this->toOutput(...), $this->configs->findAll());
        }

        $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);

        return null !== $boutique
            ? $this->toOutput($this->configs->findOneByBoutique($boutique) ?? new ChatbotConfig($boutique))
            : null;
    }

    private function toOutput(ChatbotConfig $config): ChatbotConfigOutput
    {
        $boutique = $config->getBoutique();
        $output = new ChatbotConfigOutput();
        $output->id = (string) $config->getId();
        $output->boutiqueId = (string) $boutique->getId();
        $output->mode = $config->getMode()->value;
        $output->model = $config->getModel();
        $output->systemPrompt = $config->getSystemPrompt();
        $output->temperature = $config->getTemperature();
        $output->maxTokens = $config->getMaxTokens();
        $output->isEnabled = $config->isEnabled();
        $output->chatbotEnabled = $this->capability->isVisible($boutique, $config);
        $output->aiAllowed = $this->capability->isAiAllowed($boutique);

        return $output;
    }
}
