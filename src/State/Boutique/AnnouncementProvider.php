<?php

namespace App\State\Boutique;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Boutique\AnnouncementOutput;
use App\Entity\Announcement;
use App\Repository\AnnouncementRepository;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use App\Service\FrontOfficeCacheService;
use App\State\Common\BoutiqueAwareProviderTrait;

/** @implements ProviderInterface<AnnouncementOutput> */
final readonly class AnnouncementProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private AnnouncementRepository $announcements,
        private FrontOfficeCacheService $cache,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
    ) {
    }

    /** @return list<AnnouncementOutput>|AnnouncementOutput|null */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|AnnouncementOutput|null
    {
        $boutique = $this->resolveBoutiqueFromRequest($context);

        if ($operation instanceof GetCollection && '/admin/announcements' === $operation->getUriTemplate()) {
            $criteria = $boutique ? ['boutique' => $boutique] : [];

            return array_map(
                fn (Announcement $a) => $this->toOutput($a),
                $this->announcements->findBy($criteria, ['createdAt' => 'DESC']),
            );
        }

        if ($operation instanceof GetCollection && !$boutique) {
            return array_map(
                fn (Announcement $a) => $this->toOutput($a),
                $this->announcements->findBy(['isGlobal' => true]),
            );
        }

        if (!$boutique) {
            return $operation instanceof Get ? null : [];
        }

        $boutiqueId = (string) $boutique->getId();

        if ($operation instanceof Get) {
            $a = $this->announcements->find($uriVariables['id'] ?? '');

            if ($a instanceof Announcement) {
                $announcementBoutiqueId = $a->getBoutique()?->getId()?->toRfc4122();
                if (!$a->isGlobal() && $announcementBoutiqueId !== $boutiqueId) {
                    return null;
                }
            }

            return $a instanceof Announcement ? $this->toOutput($a) : null;
        }

        return array_map(fn (array $announcement) => $this->fromCachedArray($announcement), $this->cache->getAnnouncements($boutiqueId));
    }

    public function toOutput(Announcement $a): AnnouncementOutput
    {
        $output = new AnnouncementOutput();
        $output->id = (string) $a->getId();
        $output->boutiqueId = $a->getBoutique() ? (string) $a->getBoutique()->getId() : null;
        $output->content = $a->getContent();
        $output->description = $a->getContent();
        $output->displayType = $a->getDisplayType();
        $output->type = $a->getDisplayType();
        $output->title = $a->getTitle();
        $output->subtitle = $a->getSubtitle();
        $output->backgroundColor = $a->getBackgroundColor();
        $output->textColor = $a->getTextColor();
        $output->borderColor = $a->getBorderColor();
        $output->buttonColor = $a->getButtonColor();
        $output->icon = $a->getIcon();
        $output->imageId = $a->getImage() ? (string) $a->getImage()->getId() : null;
        $output->buttonText = $a->getButtonText();
        $output->linkUrl = $a->getLinkUrl();
        $output->buttonUrl = $a->getLinkUrl();
        $output->priority = $a->getPriority();
        $output->isDismissible = $a->isDismissible();
        $output->displayMode = $a->getDisplayMode();
        $output->position = $a->getPosition();
        $output->displayPages = $a->getDisplayPages();
        $output->categoryIds = array_map('strval', $a->getTargetCategoryIds());
        $output->productIds = array_map('strval', $a->getTargetProductIds());
        $output->settings = $a->getSettings();
        $output->active = $a->isActive();
        $output->isGlobal = $a->isGlobal();
        $output->visible = $a->isVisible();
        $output->viewsCount = $a->getViewsCount();
        $output->clicksCount = $a->getClicksCount();
        $output->conversionCount = $a->getConversionCount();
        $output->startsAt = $a->getStartsAt()?->format('c');
        $output->endsAt = $a->getEndsAt()?->format('c');
        $output->createdAt = $a->getCreatedAt()->format('c');
        $output->updatedAt = $a->getUpdatedAt()?->format('c');

        return $output;
    }

    /** @param array<string, mixed> $announcement */
    private function fromCachedArray(array $announcement): AnnouncementOutput
    {
        $output = new AnnouncementOutput();
        $output->id = (string) $announcement['id'];
        $output->boutiqueId = $announcement['boutiqueId'] ? (string) $announcement['boutiqueId'] : null;
        $output->content = (string) $announcement['content'];
        $output->description = isset($announcement['description']) ? (string) $announcement['description'] : (string) $announcement['content'];
        $output->displayType = (string) $announcement['displayType'];
        $output->type = isset($announcement['type']) ? (string) $announcement['type'] : (string) $announcement['displayType'];
        $output->title = isset($announcement['title']) ? (string) $announcement['title'] : null;
        $output->subtitle = isset($announcement['subtitle']) ? (string) $announcement['subtitle'] : null;
        $output->backgroundColor = isset($announcement['backgroundColor']) ? (string) $announcement['backgroundColor'] : null;
        $output->textColor = isset($announcement['textColor']) ? (string) $announcement['textColor'] : null;
        $output->borderColor = isset($announcement['borderColor']) ? (string) $announcement['borderColor'] : null;
        $output->buttonColor = isset($announcement['buttonColor']) ? (string) $announcement['buttonColor'] : null;
        $output->icon = isset($announcement['icon']) ? (string) $announcement['icon'] : null;
        $output->imageId = isset($announcement['imageId']) ? (string) $announcement['imageId'] : null;
        $output->buttonText = isset($announcement['buttonText']) ? (string) $announcement['buttonText'] : null;
        $output->linkUrl = isset($announcement['linkUrl']) ? (string) $announcement['linkUrl'] : null;
        $output->buttonUrl = isset($announcement['buttonUrl']) ? (string) $announcement['buttonUrl'] : (isset($announcement['linkUrl']) ? (string) $announcement['linkUrl'] : null);
        $output->priority = (int) $announcement['priority'];
        $output->isDismissible = (bool) $announcement['isDismissible'];
        $output->displayMode = (string) $announcement['displayMode'];
        $output->position = (string) $announcement['position'];
        $output->displayPages = array_map('strval', $announcement['displayPages'] ?? []);
        $output->categoryIds = array_map('strval', $announcement['categoryIds'] ?? []);
        $output->productIds = array_map('strval', $announcement['productIds'] ?? []);
        $output->settings = is_array($announcement['settings'] ?? null) ? $announcement['settings'] : [];
        $output->active = (bool) $announcement['active'];
        $output->isGlobal = (bool) $announcement['isGlobal'];
        $output->visible = (bool) $announcement['visible'];
        $output->viewsCount = (int) ($announcement['viewsCount'] ?? 0);
        $output->clicksCount = (int) ($announcement['clicksCount'] ?? 0);
        $output->conversionCount = (int) ($announcement['conversionCount'] ?? 0);
        $output->startsAt = isset($announcement['startsAt']) ? (string) $announcement['startsAt'] : null;
        $output->endsAt = isset($announcement['endsAt']) ? (string) $announcement['endsAt'] : null;
        $output->createdAt = (string) $announcement['createdAt'];
        $output->updatedAt = isset($announcement['updatedAt']) ? (string) $announcement['updatedAt'] : null;

        return $output;
    }
}
