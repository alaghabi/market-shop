<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723111000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add browser deduplication hash to product and boutique reviews';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review ADD browser_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_review_browser_hash ON review (browser_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_review_browser_hash');
        $this->addSql('ALTER TABLE review DROP browser_hash');
    }
}
