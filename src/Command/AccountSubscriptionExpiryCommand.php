<?php

namespace App\Command;

use App\Service\Subscription\AccountSubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:account-subscription-expiry',
    description: 'Marque les abonnements compte expires (Active -> Expired) et invalide le cache quota.',
)]
final class AccountSubscriptionExpiryCommand extends Command
{
    public function __construct(
        private readonly AccountSubscriptionService $subscriptions,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->subscriptions->expireOverdueSubscriptions();
        if ($count > 0) {
            $this->em->flush();
        }

        $output->writeln(sprintf('Expired %d account subscription(s).', $count));

        return Command::SUCCESS;
    }
}
