<?php

namespace App\Service\Chat;

use App\Entity\ChatbotConfig;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ChatbotService
{
    private const OLLAMA_GENERATE_ENDPOINT = '/api/generate';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $ollamaBaseUrl,
        private readonly bool $globalEnabled,
        private readonly string $ollamaModel,
        private readonly string $ollamaKeepAlive,
        private readonly int $ollamaNumCtx,
        private readonly int $ollamaMaxTokens,
        private readonly int $ollamaMaxHistory,
        private readonly int $ollamaMaxMessageChars,
        private readonly int $ollamaNumThreads,
        private readonly int $ollamaTimeout,
        private readonly array $ollamaAllowedModels,
    ) {
    }

    public function isGloballyEnabled(): bool
    {
        return $this->globalEnabled;
    }

    public function generateResponse(ChatbotConfig $config, string $userMessage, array $history = []): string
    {
        if (!$this->globalEnabled) {
            return '';
        }

        $model = $this->resolveModel($config);

        $messages = $this->buildMessages($config, $userMessage, $history);
        $maxTokens = min(max(1, $config->getMaxTokens()), max(1, $this->ollamaMaxTokens));

        $payload = [
            'model' => $model,
            'prompt' => $this->formatPrompt($messages),
            'stream' => false,
            'keep_alive' => $this->ollamaKeepAlive,
            'options' => [
                'temperature' => min(1.0, max(0.0, $config->getTemperature())),
                'num_ctx' => max(256, $this->ollamaNumCtx),
                'num_predict' => $maxTokens,
                'num_thread' => max(1, $this->ollamaNumThreads),
            ],
        ];

        if ($config->getSystemPrompt()) {
            $payload['system'] = $config->getSystemPrompt();
        }

        $response = $this->httpClient->request('POST', $this->ollamaBaseUrl.self::OLLAMA_GENERATE_ENDPOINT, [
            'json' => $payload,
            'timeout' => max(5, $this->ollamaTimeout),
        ]);

        $data = $response->toArray();

        return $data['response'] ?? '';
    }

    public function resolveModel(ChatbotConfig $config): string
    {
        $requestedModel = $config->getBoutique()->getCurrentSubscription()?->getSubscriptionPlan()?->getChatbotModel()
            ?? $config->getModel();
        $requestedModel = trim($requestedModel);
        $allowedModels = array_values(array_filter(array_map(
            static fn (mixed $model): string => is_string($model) ? trim($model) : '',
            $this->ollamaAllowedModels,
        )));

        if ([] !== $allowedModels && in_array($requestedModel, $allowedModels, true)) {
            return $requestedModel;
        }

        return $this->ollamaModel;
    }

    private function buildMessages(ChatbotConfig $config, string $userMessage, array $history): array
    {
        $messages = [];
        $history = $this->ollamaMaxHistory > 0
            ? array_slice($history, -$this->ollamaMaxHistory)
            : [];

        foreach ($history as $msg) {
            $role = match ($msg['senderType'] ?? 'user') {
                'bot', 'admin' => 'assistant',
                default => 'user',
            };
            if (!empty($msg['content']) && is_string($msg['content'])) {
                $messages[] = [
                    'role' => $role,
                    'content' => $this->truncate($msg['content']),
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $this->truncate($userMessage)];

        return $messages;
    }

    private function truncate(string $value): string
    {
        $limit = max(1, $this->ollamaMaxMessageChars);
        if (strlen($value) <= $limit) {
            return $value;
        }

        if (preg_match('/^.{0,'.$limit.'}/us', $value, $matches) && isset($matches[0])) {
            return $matches[0].'...';
        }

        return substr($value, 0, $limit).'...';
    }

    private function formatPrompt(array $messages): string
    {
        $lines = [];
        foreach ($messages as $msg) {
            $prefix = 'user' === $msg['role'] ? 'Client' : 'Assistant';
            $lines[] = sprintf('%s: %s', $prefix, $msg['content']);
        }
        $lines[] = 'Assistant:';

        return implode("\n\n", $lines);
    }
}
