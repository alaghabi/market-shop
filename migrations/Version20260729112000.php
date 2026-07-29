<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729112000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add manual and AI modes to chatbot configuration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE chatbot_config ADD mode VARCHAR(16) DEFAULT 'MANUAL' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chatbot_config DROP mode');
    }
}
