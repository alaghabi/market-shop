<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:create-super-admin', description: 'Ensure the static super-admin user exists with a valid password.')]
final class CreateSuperAdminCommand extends Command
{
    private const EMAIL = 'super-admin@market-shop.local';
    private const DISPLAY_NAME = 'Super Admin';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $kernelDebug = false,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['identifier' => self::EMAIL]);

        $password = 'ahmed@1991';

        if ($user instanceof User && $user->isPasswordValid($password)) {
            $io->success(sprintf('Super-admin "%s" already exists with a valid password.', self::EMAIL));

            return Command::SUCCESS;
        }

        if ($user instanceof User) {
            $user->setPassword($password);
            $io->warning('Super-admin password updated.');
        } else {
            $user = new User(null, self::EMAIL, ['ROLE_SUPER_ADMIN'], self::DISPLAY_NAME);
            $user->setPassword($password);
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Super-admin created: %s', self::EMAIL));

        if ($this->kernelDebug) {
            $io->note(sprintf('Dev password: %s', $password));
        }

        return Command::SUCCESS;
    }
}
