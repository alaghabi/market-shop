<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729100500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create boutique publication requests.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $this->addSql('CREATE TABLE boutique_publication_request (id UUID NOT NULL, boutique_id UUID NOT NULL, status VARCHAR(16) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, handled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, handled_by VARCHAR(180) DEFAULT NULL, reason TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_PUBLICATION_REQUEST_BOUTIQUE_STATUS ON boutique_publication_request (boutique_id, status)');
        $this->addSql('ALTER TABLE boutique_publication_request ADD CONSTRAINT FK_PUBLICATION_REQUEST_BOUTIQUE FOREIGN KEY (boutique_id) REFERENCES boutique (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        $this->addSql('DROP TABLE boutique_publication_request');
    }
}
