<?php

namespace App\State\Cms;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Cms\CmsBlockInput;
use App\Dto\Cms\CmsBlockOutput;
use App\Dto\Cms\CmsPageInput;
use App\Dto\Cms\CmsPageOutput;
use App\Entity\CmsBlock;
use App\Entity\CmsPage;
use App\Enum\CmsBlockType;
use App\Enum\CmsPageStatus;
use App\Enum\CmsPageType;
use App\Repository\BoutiqueRepository;
use App\Repository\CmsPageRepository;
use App\Security\BoutiqueContext;
use App\Service\FrontOfficeCacheService;
use App\Service\Cms\CmsCacheService;
use App\Service\SeoService;
use App\State\Common\BoutiqueWriteResolverTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\Slugger\AsciiSlugger;

/** @implements ProcessorInterface<CmsPageOutput|CmsBlockOutput|null> */
final readonly class CmsPageProcessor implements ProcessorInterface
{
    use BoutiqueWriteResolverTrait;

    private AsciiSlugger $slugger;

    public function __construct(
        private BoutiqueRepository $boutiques,
        private CmsPageRepository $pages,
        private EntityManagerInterface $em,
        private BoutiqueContext $context,
        private CmsCacheService $cache,
        private SeoService $seo,
        private FrontOfficeCacheService $frontOfficeCache,
    ) {
        $this->slugger = new AsciiSlugger();
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CmsPageOutput|CmsBlockOutput|null
    {
        if ('publish_cms_page' === $operation->getName() || 'unpublish_cms_page' === $operation->getName()) {
            return $this->handlePublication($uriVariables, 'publish_cms_page' === $operation->getName());
        }

        $boutique = $this->resolveBoutiqueForWrite($data, $uriVariables, $context);

        if ($operation instanceof Delete) {
            return $this->handleDelete($uriVariables);
        }

        if (isset($uriVariables['pageId'])) {
            return $this->handleBlockMutation($data, $operation, $uriVariables, $boutique);
        }

        if ($operation instanceof Patch) {
            return $this->handlePageUpdate($data, $operation, $uriVariables, $boutique);
        }

        return $this->handlePageCreate($data, $boutique);
    }

    private function handleDelete(array $uriVariables): null
    {
        $page = $this->pages->find((string) ($uriVariables['id'] ?? ''));
        if ($page instanceof CmsPage) {
            $boutiqueId = (string) $page->getBoutique()->getId();
            $this->em->remove($page);
            $this->em->flush();
            $this->cache->invalidate($boutiqueId);
            $this->cache->invalidatePage($boutiqueId, (string) $page->getId());
            $this->frontOfficeCache->invalidateSeo($boutiqueId);
        }

        return null;
    }

    private function handlePublication(array $uriVariables, bool $publish): CmsPageOutput
    {
        $page = $this->pages->find((string) ($uriVariables['id'] ?? ''));
        if (!$page instanceof CmsPage) {
            throw new NotFoundHttpException('Page not found');
        }
        if (!$this->context->canAccessBoutique($page->getBoutique())) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Access denied');
        }

        if ($publish) {
            $page->publish();
        } else {
            $page->setStatus(CmsPageStatus::Draft);
        }

        $this->em->flush();
        $boutiqueId = (string) $page->getBoutique()->getId();
        $this->cache->invalidate($boutiqueId);
        $this->cache->invalidatePage($boutiqueId, (string) $page->getId());
        $this->frontOfficeCache->invalidateSeo($boutiqueId);

        return $this->toPageOutput($page);
    }

    private function handlePageCreate(mixed $data, \App\Entity\Boutique $boutique): CmsPageOutput
    {
        assert($data instanceof CmsPageInput);

        $type = CmsPageType::tryFrom($data->type ?? 'CUSTOM') ?? CmsPageType::Custom;
        $status = CmsPageStatus::tryFrom($data->status ?? 'DRAFT') ?? CmsPageStatus::Draft;
        $slug = $this->resolveSlug($data->slug ?? $data->title, $boutique);
        $metaTitle = $data->metaTitle ?: $this->seo->defaultMetaTitle($data->title, $boutique->getName());
        $metaDescription = $data->metaDescription ?: $this->seo->defaultMetaDescription($data->description ?? $data->content, $data->title);

        if ($data->isHomepage) {
            $this->clearHomepage($boutique);
        }

        $page = new CmsPage(
            boutique: $boutique,
            title: $data->title,
            slug: $slug,
            type: $type,
            status: $status,
            description: $data->description,
            content: $data->content,
            template: $data->template,
            isHomepage: $data->isHomepage,
            showInHeader: $data->showInHeader,
            showInFooter: $data->showInFooter,
            sortOrder: $data->sortOrder,
            metaTitle: $metaTitle,
            metaDescription: $metaDescription,
            metaKeywords: $data->metaKeywords,
            ogTitle: $data->ogTitle ?: $metaTitle,
            ogDescription: $data->ogDescription ?: $metaDescription,
            ogImage: $data->ogImage,
            canonicalUrl: $data->canonicalUrl ?: $this->seo->canonicalUrl($boutique, $slug),
        );

        if (CmsPageStatus::Published === $status) {
            $page->publish();
        }

        $this->syncBlocks($page, $data->blocks);
        $this->em->persist($page);
        $this->em->flush();
        $this->cache->invalidate((string) $boutique->getId());

        return $this->toPageOutput($page);
    }

    private function handlePageUpdate(mixed $data, Operation $operation, array $uriVariables, \App\Entity\Boutique $boutique): CmsPageOutput
    {
        unset($operation);
        assert($data instanceof CmsPageInput);

        $page = $this->pages->find((string) ($uriVariables['id'] ?? ''));
        if (!$page instanceof CmsPage || (string) $page->getBoutique()->getId() !== (string) $boutique->getId()) {
            throw new NotFoundHttpException('Page not found');
        }

        $page->setTitle($data->title);

        if (isset($data->slug) && '' !== $data->slug) {
            $slug = $this->resolveSlug($data->slug, $boutique, (string) $page->getId());
            $page->setSlug($slug);
        } else {
            $slug = $this->resolveSlug($data->title, $boutique, (string) $page->getId());
            $page->setSlug($slug);
        }

        $metaTitle = $data->metaTitle ?: $this->seo->defaultMetaTitle($data->title, $boutique->getName());
        $metaDescription = $data->metaDescription ?: $this->seo->defaultMetaDescription($data->description ?? $data->content, $data->title);

        $page->setType(CmsPageType::tryFrom($data->type ?? 'CUSTOM') ?? CmsPageType::Custom);
        $page->setDescription($data->description);
        $page->setContent($data->content);
        $page->setTemplate($data->template);
        $page->setIsHomepage($data->isHomepage);
        $page->setShowInHeader($data->showInHeader);
        $page->setShowInFooter($data->showInFooter);
        $page->setSortOrder($data->sortOrder);
        $page->setMetaTitle($metaTitle);
        $page->setMetaDescription($metaDescription);
        $page->setMetaKeywords($data->metaKeywords);
        $page->setOgTitle($data->ogTitle ?: $metaTitle);
        $page->setOgDescription($data->ogDescription ?: $metaDescription);
        $page->setOgImage($data->ogImage);
        $page->setCanonicalUrl($data->canonicalUrl ?: $this->seo->canonicalUrl($boutique, $slug));

        $status = CmsPageStatus::tryFrom($data->status ?? 'DRAFT') ?? CmsPageStatus::Draft;
        $page->setStatus($status);

        if ($data->isHomepage) {
            $this->clearHomepage($boutique, (string) $page->getId());
        }

        $this->syncBlocks($page, $data->blocks);
        $this->em->flush();
        $this->cache->invalidate((string) $boutique->getId());
        $this->cache->invalidatePage((string) $boutique->getId(), (string) $page->getId());
        $this->frontOfficeCache->invalidateSeo((string) $boutique->getId());

        return $this->toPageOutput($page);
    }

    private function handleBlockMutation(mixed $data, Operation $operation, array $uriVariables, \App\Entity\Boutique $boutique): CmsBlockOutput
    {
        $page = $this->pages->find((string) ($uriVariables['pageId'] ?? ''));
        if (!$page instanceof CmsPage || (string) $page->getBoutique()->getId() !== (string) $boutique->getId()) {
            throw new NotFoundHttpException('Page not found');
        }

        if ($operation instanceof Patch) {
            assert($data instanceof CmsBlockInput);
            $block = $this->em->getRepository(CmsBlock::class)->find((string) ($uriVariables['id'] ?? ''));
            if (!$block instanceof CmsBlock || (string) $block->getPage()->getId() !== (string) $page->getId()) {
                throw new NotFoundHttpException('Block not found');
            }

            $block->setType(CmsBlockType::tryFrom($data->type ?? 'TEXT') ?? CmsBlockType::Text);
            $block->setTitle($data->title);
            $block->setContent($data->content);
            $block->setSettings($data->settings);
            $block->setSortOrder($data->sortOrder);
            $block->setIsActive($data->isActive);
            $this->em->flush();
            $this->cache->invalidate((string) $boutique->getId());
            $this->cache->invalidatePage((string) $boutique->getId(), (string) $page->getId());
            $this->frontOfficeCache->invalidateSeo((string) $boutique->getId());

            return $this->toBlockOutput($block);
        }

        assert($data instanceof CmsBlockInput);
        $block = new CmsBlock(
            page: $page,
            type: CmsBlockType::tryFrom($data->type ?? 'TEXT') ?? CmsBlockType::Text,
            title: $data->title,
            content: $data->content,
            settings: $data->settings,
            sortOrder: $data->sortOrder,
            isActive: $data->isActive,
        );

        $this->em->persist($block);
        $this->em->flush();
        $this->cache->invalidate((string) $boutique->getId());
        $this->cache->invalidatePage((string) $boutique->getId(), (string) $page->getId());
        $this->frontOfficeCache->invalidateSeo((string) $boutique->getId());

        return $this->toBlockOutput($block);
    }

    private function resolveSlug(string $text, \App\Entity\Boutique $boutique, ?string $excludeId = null): string
    {
        $slug = $this->slugger->slug($text)->lower()->toString();
        if ('' === $slug) {
            $slug = 'page';
        }

        $original = $slug;
        $i = 2;
        while ($this->pages->slugExistsInBoutique($boutique, $slug, $excludeId)) {
            $slug = $original.'-'.$i;
            ++$i;
        }

        return $slug;
    }

    private function clearHomepage(\App\Entity\Boutique $boutique, ?string $excludeId = null): void
    {
        $current = $this->pages->findHomepage($boutique);
        if ($current instanceof CmsPage && (null === $excludeId || (string) $current->getId() !== $excludeId)) {
            $current->setIsHomepage(false);
        }
    }

    /** @param list<array{type: string, title?: ?string, content?: ?string, settings?: ?array, sortOrder?: int, isActive?: bool}> $blocksData */
    private function syncBlocks(CmsPage $page, array $blocksData): void
    {
        $existingIds = [];
        foreach ($page->getBlocks() as $existingBlock) {
            $existingIds[(string) $existingBlock->getId()] = $existingBlock;
        }

        $seenIds = [];
        foreach ($blocksData as $i => $blockData) {
            $type = CmsBlockType::tryFrom($blockData['type'] ?? 'TEXT') ?? CmsBlockType::Text;
            $block = null;

            $block = new CmsBlock(
                page: $page,
                type: $type,
                title: $blockData['title'] ?? null,
                content: $blockData['content'] ?? null,
                settings: $blockData['settings'] ?? null,
                sortOrder: $blockData['sortOrder'] ?? $i,
                isActive: $blockData['isActive'] ?? true,
            );

            $page->addBlock($block);
            $this->em->persist($block);
        }

        foreach ($existingIds as $existingId => $existingBlock) {
            if (!isset($seenIds[$existingId])) {
                $page->removeBlock($existingBlock);
                $this->em->remove($existingBlock);
            }
        }
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
