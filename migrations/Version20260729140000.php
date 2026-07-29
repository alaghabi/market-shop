<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the Keycloak subject identifier to application users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD keycloak_subject VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_app_user_keycloak_subject ON app_user (keycloak_subject)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_app_user_keycloak_subject');
        $this->addSql('ALTER TABLE app_user DROP keycloak_subject');
    }
}
