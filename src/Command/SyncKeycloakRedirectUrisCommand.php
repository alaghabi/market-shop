<?php

namespace App\Command;

use App\Repository\BoutiqueRepository;
use App\Service\Auth\KeycloakRedirectUriSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:keycloak:sync-redirect-uris',
    description: 'Synchronize published boutique redirect URIs with the Keycloak web client.',
)]
final class SyncKeycloakRedirectUrisCommand extends Command
{
    public function __construct(
        private readonly BoutiqueRepository $boutiques,
        private readonly KeycloakRedirectUriSynchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        unset($input);

        try {
            $count = $this->synchronizer->syncAll($this->boutiques->findAll());
            $output->writeln(sprintf('Keycloak synchronisé : %d redirect URI(s) ajoutée(s).', $count));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>Synchronisation Keycloak impossible : '.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }
    }
}
