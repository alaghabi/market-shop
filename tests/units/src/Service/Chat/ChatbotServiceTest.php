<?php

namespace App\Tests\Service\Chat;

use App\Entity\Boutique;
use App\Entity\ChatbotConfig;
use App\Service\Chat\ChatbotService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ChatbotServiceTest extends TestCase
{
    public function testGenerationUsesConfiguredResourceLimits(): void
    {
        $requestOptions = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestOptions): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://ollama/api/generate', $url);
            $requestOptions = $options;

            return new MockResponse(json_encode(['response' => 'Réponse courte'], JSON_THROW_ON_ERROR));
        });
        $service = $this->createService($client, maxHistory: 2, maxMessageChars: 20);
        $config = new ChatbotConfig(new Boutique('Demo', 'demo'));
        $config->setMaxTokens(1024);

        $response = $service->generateResponse($config, 'Question finale très longue à limiter', [
            ['senderType' => 'user', 'content' => 'Ancien message à supprimer'],
            ['senderType' => 'bot', 'content' => 'Réponse intermédiaire'],
            ['senderType' => 'user', 'content' => 'Dernier message'],
        ]);

        self::assertSame('Réponse courte', $response);
        self::assertIsArray($requestOptions);
        $payload = json_decode($requestOptions['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('5m', $payload['keep_alive']);
        self::assertSame(1024, $payload['options']['num_ctx']);
        self::assertSame(256, $payload['options']['num_predict']);
        self::assertSame(2, $payload['options']['num_thread']);
        self::assertStringNotContainsString('Ancien message', $payload['prompt']);
        self::assertStringContainsString('Dernier message', $payload['prompt']);
        self::assertStringContainsString('...', $payload['prompt']);
    }

    public function testDisallowedModelFallsBackToConfiguredDefault(): void
    {
        $service = $this->createService(new MockHttpClient(), allowedModels: ['llama3.2:1b']);
        $config = new ChatbotConfig(new Boutique('Demo', 'demo'));
        $config->setModel('qwen2.5:7b');

        self::assertSame('llama3.2:1b', $service->resolveModel($config));
    }

    private function createService(
        MockHttpClient $client,
        int $maxHistory = 8,
        int $maxMessageChars = 1200,
        array $allowedModels = ['llama3.2:1b'],
    ): ChatbotService {
        return new ChatbotService(
            httpClient: $client,
            ollamaBaseUrl: 'http://ollama',
            globalEnabled: true,
            ollamaModel: 'llama3.2:1b',
            ollamaKeepAlive: '5m',
            ollamaNumCtx: 1024,
            ollamaMaxTokens: 256,
            ollamaMaxHistory: $maxHistory,
            ollamaMaxMessageChars: $maxMessageChars,
            ollamaNumThreads: 2,
            ollamaTimeout: 45,
            ollamaAllowedModels: $allowedModels,
        );
    }
}
