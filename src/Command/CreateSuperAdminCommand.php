<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:create-super-admin', description: 'Ensure the configured super-admin user exists with a valid password.')]
final class CreateSuperAdminCommand extends Command
{
    private const EMAIL = 'super-hanooti@gmail.com';
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

        $password = $_ENV['SUPER_ADMIN_PASSWORD'] ?? $_SERVER['SUPER_ADMIN_PASSWORD'] ?? null;
        if (!is_string($password) || '' === trim($password)) {
            if (!$this->kernelDebug) {
                $io->error('SUPER_ADMIN_PASSWORD must be configured outside the repository in production.');

                return Command::FAILURE;
            }

            $password = bin2hex(random_bytes(16));
            $io->note('A development password was generated for this run.');
        }

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
            $io->note(sprintf('Development password: %s', $password));
        }

        return Command::SUCCESS;
    }
}
