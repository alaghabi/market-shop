<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the unused example schema and align notification log index names.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $this->addSql('DROP TABLE IF EXISTS example');
        $this->addSql('DROP INDEX IF EXISTS idx_review_browser_hash');
        $this->addSql('ALTER INDEX IF EXISTS uniq_notification_log_deduplication_key RENAME TO UNIQ_ED15DF2EE427CD1');
    }

    public function down(Schema $schema): void
    {
        unset($schema);
    }
}
