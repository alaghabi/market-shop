<?php

namespace App\State\SubscriptionPlan;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\SubscriptionPlan\SubscriptionPlanInput;
use App\Dto\SubscriptionPlan\SubscriptionPlanOutput;
use App\Entity\PlanQuota;
use App\Entity\SubscriptionPlan;
use App\Entity\SubscriptionModule;
use App\Repository\PlanQuotaRepository;
use App\Repository\QuotaDefinitionRepository;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionModuleRepository;
use App\Repository\SubscriptionPlanModuleRepository;
use App\Repository\ThemeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SubscriptionPlanProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SubscriptionPlanRepository $repository,
        private readonly ThemeRepository $themes,
        private readonly QuotaDefinitionRepository $quotaDefinitions,
        private readonly PlanQuotaRepository $planQuotas,
        private readonly SubscriptionModuleRepository $subscriptionModules,
        private readonly SubscriptionPlanModuleRepository $modules,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?SubscriptionPlanOutput
    {
        if ($operation instanceof Delete) {
            $entity = $this->findEntity((string) ($uriVariables['id'] ?? ''));
            $this->em->remove($entity);
            $this->em->flush();

            return null;
        }

        if (isset($uriVariables['id'])) {
            return $this->update((string) $uriVariables['id'], $data);
        }

        return $this->create($data);
    }

    private function create(mixed $data): SubscriptionPlanOutput
    {
        if (!$data instanceof SubscriptionPlanInput) {
            throw new \InvalidArgumentException('Expected SubscriptionPlanInput');
        }

        $entity = new SubscriptionPlan(
            name: $data->name,
            description: $data->description,
            durationMonths: $data->durationMonths,
            priceTnd: $data->priceTnd,
            isFree: $data->isFree,
            isVisible: $data->isVisible,
            isActive: $data->isActive,
            modules: $data->modules,
            currency: $data->currency,
            displayOrder: $data->displayOrder,
        );
        $this->em->persist($entity);
        $this->syncModules($entity, $data->modules);
        $this->syncThemes($entity, $data->themeCodes);
        $this->syncQuotas($entity, $data->quotas);
        $this->em->flush();

        return $this->toOutput($entity);
    }

    private function update(string $id, mixed $data): SubscriptionPlanOutput
    {
        $entity = $this->findEntity($id);

        if (!$data instanceof SubscriptionPlanInput) {
            throw new \InvalidArgumentException('Expected SubscriptionPlanInput');
        }

        $entity->setName($data->name);
        $entity->setDescription($data->description);
        $entity->setDurationMonths($data->durationMonths);
        $entity->setPriceTnd($data->priceTnd);
        $entity->setIsFree($data->isFree);
        $entity->setIsVisible($data->isVisible);
        $entity->setIsActive($data->isActive);
        $this->syncModules($entity, $data->modules);
        $entity->setCurrency($data->currency);
        $entity->setDisplayOrder($data->displayOrder);
        $this->syncThemes($entity, $data->themeCodes);
        $this->syncQuotas($entity, $data->quotas);
        $this->em->flush();

        return $this->toOutput($entity);
    }

    /** @param list<string>|null $moduleCodes */
    private function syncModules(SubscriptionPlan $entity, ?array $moduleCodes): void
    {
        if (null === $moduleCodes) {
            return;
        }

        $selectedCodes = [];
        $selectedModules = [];
        foreach ($moduleCodes as $code) {
            if (!\is_string($code) || '' === trim($code)) {
                throw new BadRequestHttpException('Chaque module sélectionné doit être valide.');
            }

            $code = trim($code);
            if (isset($selectedCodes[$code])) {
                continue;
            }

            $module = $this->modules->findOneByCode($code);
            if (null === $module) {
                throw new BadRequestHttpException(sprintf('Module inconnu : %s.', $code));
            }

            $selectedCodes[$code] = true;
            $selectedModules[] = $module;
        }

        $entity->setModules(array_keys($selectedCodes));

        foreach ($this->subscriptionModules->findByPlan($entity) as $subscriptionModule) {
            $subscriptionModule->setAllowed(isset($selectedCodes[$subscriptionModule->getModule()->getCode()]));
        }

        foreach ($selectedModules as $module) {
            if (null !== $this->subscriptionModules->findOneByPlanAndModule($entity, $module)) {
                continue;
            }

            $subscriptionModule = new SubscriptionModule(plan: $entity, module: $module, isAllowed: true);
            $this->em->persist($subscriptionModule);
            $entity->addSubscriptionModule($subscriptionModule);
        }
    }

    /** @param list<string>|null $themeCodes */
    private function syncThemes(SubscriptionPlan $entity, ?array $themeCodes): void
    {
        if (null === $themeCodes) {
            return;
        }

        $themes = [];
        foreach ($themeCodes as $code) {
            $theme = $this->themes->findOneByCode($code);
            if (null !== $theme) {
                $themes[] = $theme;
            }
        }

        $entity->setThemes(new ArrayCollection($themes));
    }

    /** @param list<array{quotaCode: string, limitValue: int|null}>|null $quotas */
    private function syncQuotas(SubscriptionPlan $entity, ?array $quotas): void
    {
        if (null === $quotas) {
            return;
        }

        foreach ($quotas as $item) {
            $quotaCode = $item['quotaCode'] ?? null;
            if (!\is_string($quotaCode) || '' === $quotaCode) {
                continue;
            }

            $quota = $this->quotaDefinitions->findOneByCode($quotaCode);
            if (null === $quota) {
                continue;
            }

            $limitValue = \array_key_exists('limitValue', $item) ? $item['limitValue'] : null;

            $planQuota = $this->planQuotas->findOneByPlanAndQuota($entity, $quota);
            if (null !== $planQuota) {
                $planQuota->setLimitValue($limitValue);
                continue;
            }

            $planQuota = new PlanQuota(plan: $entity, quota: $quota, limitValue: $limitValue);
            $this->em->persist($planQuota);
            $entity->addPlanQuota($planQuota);
        }
    }

    private function findEntity(string $id): SubscriptionPlan
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            throw new NotFoundHttpException('Subscription plan not found');
        }

        return $entity;
    }

    private function toOutput(SubscriptionPlan $entity): SubscriptionPlanOutput
    {
        $output = new SubscriptionPlanOutput();
        $output->id = (string) $entity->getId();
        $output->name = $entity->getName();
        $output->description = $entity->getDescription();
        $output->durationMonths = $entity->getDurationMonths();
        $output->priceTnd = $entity->getPriceTnd();
        $output->isFree = $entity->isFree();
        $output->isVisible = $entity->isVisible();
        $output->isActive = $entity->isActive();
        $allowedModuleCodes = $this->subscriptionModules->findAllowedModuleCodes($entity);
        $output->modules = [] !== $allowedModuleCodes ? $allowedModuleCodes : $entity->getModules();
        $output->currency = $entity->getCurrency();
        $output->displayOrder = $entity->getDisplayOrder();
        $output->themes = array_map(
            static fn ($theme) => ['id' => (string) $theme->getId(), 'code' => $theme->getCode(), 'name' => $theme->getName()],
            $entity->getThemes()->toArray(),
        );

        $quotas = [];
        foreach ($entity->getPlanQuotas() as $planQuota) {
            $quotas[] = [
                'quotaCode' => $planQuota->getQuota()->getCode(),
                'quotaName' => $planQuota->getQuota()->getName(),
                'limitValue' => $planQuota->getLimitValue(),
            ];
        }
        $output->quotas = $quotas;

        $output->createdAt = $entity->getCreatedAt()->format('c');
        $output->updatedAt = $entity->getUpdatedAt()?->format('c');

        return $output;
    }
}
