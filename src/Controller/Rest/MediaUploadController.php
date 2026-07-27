<?php

namespace App\Controller\Rest;

use ApiPlatform\Metadata\Post;
use App\State\Media\MediaProcessor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final readonly class MediaUploadController
{
    public function __construct(private MediaProcessor $processor)
    {
    }

    #[Route('/api/media/upload', name: 'api_media_upload', methods: ['POST'])]
    #[IsGranted('ROLE_BOUTIQUE_ADMIN')]
    public function __invoke(Request $request): JsonResponse
    {
        $media = $this->processor->process(
            null,
            new Post(uriTemplate: '/media/upload'),
            [],
            ['request' => $request],
        );

        if (null === $media) {
            return new JsonResponse(['detail' => 'Le fichier est invalide.'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'id' => $media->id,
            'boutiqueId' => $media->boutiqueId,
            'type' => $media->type,
            'fileName' => $media->fileName,
            'url' => $media->url,
            'thumbnailUrl' => $media->thumbnailUrl,
        ], Response::HTTP_CREATED);
    }
}
