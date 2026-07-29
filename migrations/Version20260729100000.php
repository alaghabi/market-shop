<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification timestamps and single-use verification tokens.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $this->addSql('ALTER TABLE app_user ADD email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("UPDATE app_user SET email_verified_at = NOW() WHERE roles::jsonb @> '[\"ROLE_BOUTIQUE_ADMIN\"]'::jsonb OR roles::jsonb @> '[\"ROLE_CAISSIER\"]'::jsonb OR roles::jsonb @> '[\"ROLE_EMPLOYEE\"]'::jsonb");
        $this->addSql('CREATE TABLE email_verification_token (id UUID NOT NULL, user_id UUID NOT NULL, boutique_id UUID DEFAULT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EMAIL_VERIFICATION_TOKEN_HASH ON email_verification_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_EMAIL_VERIFICATION_USER_EXPIRY ON email_verification_token (user_id, expires_at)');
        $this->addSql('ALTER TABLE email_verification_token ADD CONSTRAINT FK_EMAIL_VERIFICATION_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email_verification_token ADD CONSTRAINT FK_EMAIL_VERIFICATION_BOUTIQUE FOREIGN KEY (boutique_id) REFERENCES boutique (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        $this->addSql('DROP TABLE email_verification_token');
        $this->addSql('ALTER TABLE app_user DROP email_verified_at');
    }
}
