<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expiring password reset tokens for global and boutique customer accounts.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $this->addSql('CREATE TABLE password_reset_token (id UUID NOT NULL, user_id UUID NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PASSWORD_RESET_TOKEN_HASH ON password_reset_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_PASSWORD_RESET_USER_EXPIRY ON password_reset_token (user_id, expires_at)');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_PASSWORD_RESET_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        $this->addSql('DROP TABLE password_reset_token');
    }
}
