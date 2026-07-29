<?php

namespace App\Controller;

use App\Service\AppConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PublicAppConfigController
{
    public function __construct(private AppConfigService $config)
    {
    }

    #[Route('/api/public/app-config', name: 'api_public_app_config', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->config->publicConfig());
    }
}
