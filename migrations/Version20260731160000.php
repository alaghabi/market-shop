<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable renewal_price_tnd on subscription_plan (null = use price_tnd)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription_plan ADD renewal_price_tnd INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription_plan DROP renewal_price_tnd');
    }
}
