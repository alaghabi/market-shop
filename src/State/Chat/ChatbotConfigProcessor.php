<?php

namespace App\State\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Chat\ChatbotConfigInput;
use App\Dto\Chat\ChatbotConfigOutput;
use App\Entity\ChatbotConfig;
use App\Enum\ChatbotMode;
use App\Factory\RedisFactory;
use App\Repository\BoutiqueRepository;
use App\Repository\ChatbotConfigRepository;
use App\Security\BoutiqueContext;
use App\Service\Chat\ChatbotCapabilityService;
use App\State\Common\BoutiqueAwareProviderTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ChatbotConfigProcessor implements ProcessorInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private BoutiqueRepository $boutiques,
        private ChatbotConfigRepository $configs,
        private BoutiqueContext $context,
        private ChatbotCapabilityService $capability,
        private RedisFactory $redisFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChatbotConfigOutput
    {
        unset($operation);
        if (!$data instanceof ChatbotConfigInput) {
            throw new \InvalidArgumentException('Expected ChatbotConfigInput');
        }

        $config = isset($uriVariables['id'])
            ? $this->configs->find($uriVariables['id'])
            : null;

        if (isset($uriVariables['id']) && !$config instanceof ChatbotConfig) {
            throw new NotFoundHttpException('Chatbot configuration not found.');
        }

        if ($config instanceof ChatbotConfig) {
            $boutique = $config->getBoutique();
        } else {
            $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);
            if (null === $boutique && null !== $data->boutiqueId) {
                $boutique = $this->boutiques->find((string) $data->boutiqueId);
            }
            if (null === $boutique) {
                throw new BadRequestHttpException('Boutique is required.');
            }
        }

        if (!$this->context->canAccessBoutique($boutique)) {
            throw new AccessDeniedHttpException('You cannot manage this boutique chatbot.');
        }

        if (!$config instanceof ChatbotConfig) {
            $config = $this->configs->findOneByBoutique($boutique) ?? new ChatbotConfig($boutique);
            $this->em->persist($config);
        }

        $mode = $config->getMode();
        if (null !== $data->mode) {
            $mode = ChatbotMode::tryFrom(strtoupper(trim($data->mode)));
            if (null === $mode) {
                throw new BadRequestHttpException('Chatbot mode must be MANUAL or AI.');
            }
            $config->setMode($mode);
        }

        if (null !== $data->model) {
            $config->setModel(trim($data->model));
        }
        if (null !== $data->systemPrompt) {
            $config->setSystemPrompt($data->systemPrompt);
        }
        if (null !== $data->temperature) {
            if ($data->temperature < 0 || $data->temperature > 1) {
                throw new BadRequestHttpException('Temperature must be between 0 and 1.');
            }
            $config->setTemperature($data->temperature);
        }
        if (null !== $data->maxTokens) {
            if ($data->maxTokens < 1 || $data->maxTokens > 2048) {
                throw new BadRequestHttpException('maxTokens must be between 1 and 2048.');
            }
            $config->setMaxTokens($data->maxTokens);
        }
        if (null !== $data->isEnabled) {
            $config->setIsEnabled($data->isEnabled);
        }

        if ($config->isEnabled() && ChatbotMode::Ai === $config->getMode() && !$this->capability->isAiAllowed($boutique)) {
            throw new AccessDeniedHttpException('The AI chatbot requires the chatbot module or an active extension.');
        }

        $this->em->flush();
        $this->invalidateCache((string) $boutique->getId());

        return $this->toOutput($config);
    }

    private function invalidateCache(string $boutiqueId): void
    {
        try {
            $redis = $this->redisFactory->create();
            if (null !== $redis) {
                $redis->del('shop.'.$boutiqueId.'.chatbot_config');
            }
        } catch (\RedisException) {
        }
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
