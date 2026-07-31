<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add partial unique index for active account subscriptions and (user_id, status) lookup index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_account_subscription_user_status ON account_subscription (user_id, status)');
        $this->addSql("CREATE UNIQUE INDEX IF NOT EXISTS uniq_account_subscription_user_active ON account_subscription (user_id) WHERE status = 'active'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_account_subscription_user_active');
        $this->addSql('DROP INDEX IF EXISTS IDX_account_subscription_user_status');
    }
}
