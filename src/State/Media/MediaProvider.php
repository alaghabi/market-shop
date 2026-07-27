<?php

namespace App\State\Media;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Media\MediaOutput;
use App\Entity\Media;
use App\Repository\MediaRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<MediaOutput> */
final readonly class MediaProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private MediaRepository $media,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|MediaOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $boutique = $this->resolveBoutiqueFromRequest($context);
        if (!$boutique) {
            return $operation instanceof Get
                ? null
                : new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        if ($operation instanceof Get) {
            $media = $this->media->find((string) ($uriVariables['id'] ?? ''));

            return $media instanceof Media && (string) $media->getBoutique()->getId() === (string) $boutique->getId()
                ? $this->toOutput($media)
                : null;
        }

        $result = $this->media->findForBackoffice(
            $boutique,
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map([$this, 'toOutput'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function toOutput(Media $media): MediaOutput
    {
        $output = new MediaOutput();
        $output->id = (string) $media->getId();
        $output->boutiqueId = (string) $media->getBoutique()->getId();
        $output->type = $media->getType()->value;
        $output->originalName = $media->getOriginalName();
        $output->fileName = $media->getFileName();
        $output->extension = $media->getExtension();
        $output->mimeType = $media->getMimeType();
        $output->size = $media->getSize();
        $output->width = $media->getWidth();
        $output->height = $media->getHeight();
        $output->duration = $media->getDuration();
        $output->url = $media->getUrl();
        $output->thumbnailUrl = $media->getThumbnailUrl();
        $output->compressedUrl = $media->getCompressedUrl();
        $output->altText = $media->getAltText();
        $output->isPublic = $media->isPublic();
        $output->createdAt = $media->getCreatedAt();
        $output->updatedAt = $media->getUpdatedAt();

        return $output;
    }
}
