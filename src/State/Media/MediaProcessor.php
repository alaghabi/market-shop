<?php

namespace App\State\Media;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Media\MediaOutput;
use App\Entity\Media;
use App\Repository\BoutiqueRepository;
use App\Repository\MediaRepository;
use App\Security\BoutiqueContext;
use App\Service\MediaService;
use App\Service\Media\MediaCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<MediaOutput> */
final readonly class MediaProcessor implements ProcessorInterface
{
    public function __construct(
        private BoutiqueRepository $boutiques,
        private MediaRepository $media,
        private EntityManagerInterface $em,
        private BoutiqueContext $context,
        private MediaService $mediaService,
        private MediaCacheService $cache,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?MediaOutput
    {
        unset($data);

        $request = $context['request'] ?? null;
        $boutiqueId = $uriVariables['boutiqueId']
            ?? $request?->attributes->get('_boutique_id')
            ?? $request?->query->get('boutiqueId');
        $boutique = $boutiqueId ? $this->boutiques->findBySlugOrId((string) $boutiqueId) : null;

        if (!$boutique && $request?->attributes->has('_boutique')) {
            $boutique = $request->attributes->get('_boutique');
        }

        if (!$boutique) {
            throw new NotFoundHttpException('Boutique not found');
        }
        if (!$this->context->canAccessBoutique($boutique)) {
            throw new AccessDeniedHttpException('Access denied');
        }

        if ($operation instanceof Delete) {
            $entity = $this->media->find((string) ($uriVariables['id'] ?? ''));
            if ($entity instanceof Media) {
                $this->mediaService->deleteFile($entity->getPath());
                $this->em->remove($entity);
                $this->em->flush();
                $this->cache->invalidate($boutique);
            }

            return null;
        }

        if (!$request) {
            throw new \InvalidArgumentException('No request context');
        }

        $file = $request->files->get('file');
        $altText = $request->request->get('altText');
        $isPublic = (bool) $request->request->get('isPublic', true);
        $contextDir = $request->request->get('context', 'general');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new \InvalidArgumentException('Valid file is required');
        }

        $result = $this->mediaService->upload($file, (string) $boutique->getId(), $contextDir);

        $media = new Media(
            boutique: $boutique,
            type: $result->type,
            originalName: $result->originalName,
            fileName: $result->fileName,
            extension: $result->extension,
            mimeType: $result->mimeType,
            size: $result->size,
            width: $result->width,
            height: $result->height,
            duration: $result->duration,
            path: $result->path,
            altText: $altText ?: null,
            isPublic: $isPublic,
        );

        if (!empty($result->thumbnails)) {
            $media->setThumbnailPath($result->thumbnails['small'] ?? null);
        }

        $this->em->persist($media);
        $this->em->flush();
        $this->cache->invalidate($boutique);

        $output = new MediaOutput();
        $output->id = (string) $media->getId();
        $output->boutiqueId = (string) $boutique->getId();
        $output->type = $result->type->value;
        $output->originalName = $result->originalName;
        $output->fileName = $result->fileName;
        $output->extension = $result->extension;
        $output->mimeType = $result->mimeType;
        $output->size = $result->size;
        $output->width = $result->width;
        $output->height = $result->height;
        $output->duration = $result->duration;
        $output->url = $media->getUrl();
        $output->thumbnailUrl = $media->getThumbnailUrl();
        $output->altText = $media->getAltText();
        $output->isPublic = $isPublic;
        $output->createdAt = $media->getCreatedAt();

        return $output;
    }
}
