<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification deduplication keys for reliable asynchronous delivery.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $this->addSql('ALTER TABLE notification_log ADD deduplication_key VARCHAR(160) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_NOTIFICATION_LOG_DEDUPLICATION_KEY ON notification_log (deduplication_key)');
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        $this->addSql('DROP INDEX UNIQ_NOTIFICATION_LOG_DEDUPLICATION_KEY');
        $this->addSql('ALTER TABLE notification_log DROP deduplication_key');
    }
}
