<?php

namespace App\State\Cms;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Cms\CmsBlockOutput;
use App\Dto\Cms\CmsPageOutput;
use App\Entity\CmsBlock;
use App\Entity\CmsPage;
use App\Repository\BoutiqueRepository;
use App\Repository\CmsPageRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<CmsPageOutput|CmsBlockOutput> */
final readonly class CmsPageProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private CmsPageRepository $pages,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|CmsPageOutput|CmsBlockOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);

        if ('public_cms_page' === $operation->getName()) {
            if (!$boutique) {
                return null;
            }

            $page = $this->pages->findOneByBoutiqueAndSlug($boutique, (string) ($uriVariables['slug'] ?? ''));

            return $page instanceof CmsPage && 'PUBLISHED' === $page->getStatus()->value
                ? $this->toPageOutput($page)
                : null;
        }

        if (!$boutique) {
            if (!$this->context->isSuperAdmin()) {
                return $operation instanceof Get
                    ? null
                    : new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
            }

            if ($operation instanceof Get) {
                $page = $this->pages->find((string) ($uriVariables['id'] ?? ''));

                return $page instanceof CmsPage ? $this->toPageOutput($page) : null;
            }

            $result = $this->pages->findForBackoffice(
                null,
                $pagination['page'],
                $pagination['itemsPerPage'],
            );

            return new BackofficePaginator(
                array_map([$this, 'toPageOutput'], $result['items']),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        if ($operation instanceof Get && isset($uriVariables['blockId'])) {
            return $this->getBlockOutput($uriVariables);
        }

        if ($operation instanceof GetCollection && isset($uriVariables['id'])) {
            return $this->getBlockCollectionOutput($boutique, $uriVariables, $pagination);
        }

        if ($operation instanceof Get) {
            $page = $this->pages->find((string) ($uriVariables['id'] ?? ''));

            return $page instanceof CmsPage && (string) $page->getBoutique()->getId() === (string) $boutique->getId()
                ? $this->toPageOutput($page)
                : null;
        }

        $result = $this->pages->findForBackoffice(
            $boutique,
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map([$this, 'toPageOutput'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function getBlockOutput(array $uriVariables): ?CmsBlockOutput
    {
        $page = $this->pages->find((string) ($uriVariables['id'] ?? ''));
        if (!$page) {
            return null;
        }

        $block = $page->getBlocks()->filter(
            fn (CmsBlock $b) => (string) $b->getId() === (string) ($uriVariables['blockId'] ?? ''),
        )->first();

        return $block ? $this->toBlockOutput($block) : null;
    }

    /** @param array{page: int, itemsPerPage: int} $pagination */
    private function getBlockCollectionOutput(\App\Entity\Boutique $boutique, array $uriVariables, array $pagination): PaginatorInterface
    {
        $page = $this->pages->find((string) ($uriVariables['id'] ?? ''));
        if (!$page || (string) $page->getBoutique()->getId() !== (string) $boutique->getId()) {
            return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        $blocks = array_map(
            [$this, 'toBlockOutput'],
            $page->getBlocks()->toArray(),
        );

        return new BackofficePaginator(
            array_slice($blocks, ($pagination['page'] - 1) * $pagination['itemsPerPage'], $pagination['itemsPerPage']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            count($blocks),
        );
    }

    private function toPageOutput(CmsPage $page): CmsPageOutput
    {
        $output = new CmsPageOutput();
        $output->id = (string) $page->getId();
        $output->boutiqueId = (string) $page->getBoutique()->getId();
        $output->title = $page->getTitle();
        $output->slug = $page->getSlug();
        $output->type = $page->getType()->value;
        $output->status = $page->getStatus()->value;
        $output->description = $page->getDescription();
        $output->content = $page->getContent();
        $output->template = $page->getTemplate();
        $output->isHomepage = $page->isHomepage();
        $output->showInHeader = $page->showInHeader();
        $output->showInFooter = $page->showInFooter();
        $output->sortOrder = $page->getSortOrder();
        $output->publishedAt = $page->getPublishedAt();
        $output->metaTitle = $page->getMetaTitle();
        $output->metaDescription = $page->getMetaDescription();
        $output->metaKeywords = $page->getMetaKeywords();
        $output->ogTitle = $page->getOgTitle();
        $output->ogDescription = $page->getOgDescription();
        $output->ogImage = $page->getOgImage();
        $output->canonicalUrl = $page->getCanonicalUrl();
        $output->createdAt = $page->getCreatedAt();
        $output->updatedAt = $page->getUpdatedAt();
        $output->blocks = array_map(
            [$this, 'toBlockOutput'],
            $page->getBlocks()->toArray(),
        );

        return $output;
    }

    private function toBlockOutput(CmsBlock $block): CmsBlockOutput
    {
        $output = new CmsBlockOutput();
        $output->id = (string) $block->getId();
        $output->pageId = (string) $block->getPage()->getId();
        $output->type = $block->getType()->value;
        $output->title = $block->getTitle();
        $output->content = $block->getContent();
        $output->settings = $block->getSettings();
        $output->sortOrder = $block->getSortOrder();
        $output->isActive = $block->isActive();
        $output->createdAt = $block->getCreatedAt();
        $output->updatedAt = $block->getUpdatedAt();

        return $output;
    }
}
