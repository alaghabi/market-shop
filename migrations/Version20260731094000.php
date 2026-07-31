<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731094000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create account-level subscriptions with bundled extensions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE account_subscription (status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, changed_by VARCHAR(180) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, user_id UUID NOT NULL, subscription_plan_id UUID DEFAULT NULL, replaced_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3A1E7295A76ED395 ON account_subscription (user_id)');
        $this->addSql('CREATE INDEX IDX_account_subscription_user_status ON account_subscription (user_id, status)');
        $this->addSql("CREATE UNIQUE INDEX uniq_account_subscription_user_active ON account_subscription (user_id) WHERE status = 'active'");
        $this->addSql('CREATE INDEX IDX_3A1E72959B8CE200 ON account_subscription (subscription_plan_id)');
        $this->addSql('CREATE INDEX IDX_3A1E72959AC69B54 ON account_subscription (replaced_by_id)');
        $this->addSql('CREATE TABLE account_subscription_extension (activated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, activated_by VARCHAR(180) DEFAULT NULL, is_active BOOLEAN NOT NULL, expiry_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, account_subscription_id UUID NOT NULL, extension_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_account_subscription_extension_grant ON account_subscription_extension (account_subscription_id, extension_id)');
        $this->addSql('CREATE INDEX IDX_4EDEED917A9F9906 ON account_subscription_extension (account_subscription_id)');
        $this->addSql('CREATE INDEX IDX_4EDEED91812D5EB ON account_subscription_extension (extension_id)');
        $this->addSql('ALTER TABLE account_subscription ADD CONSTRAINT FK_3A1E7295A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE account_subscription ADD CONSTRAINT FK_3A1E72959B8CE200 FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plan (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE account_subscription ADD CONSTRAINT FK_3A1E72959AC69B54 FOREIGN KEY (replaced_by_id) REFERENCES account_subscription (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE account_subscription_extension ADD CONSTRAINT FK_4EDEED917A9F9906 FOREIGN KEY (account_subscription_id) REFERENCES account_subscription (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE account_subscription_extension ADD CONSTRAINT FK_4EDEED91812D5EB FOREIGN KEY (extension_id) REFERENCES extension (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_subscription_extension DROP CONSTRAINT FK_4EDEED91812D5EB');
        $this->addSql('ALTER TABLE account_subscription_extension DROP CONSTRAINT FK_4EDEED917A9F9906');
        $this->addSql('ALTER TABLE account_subscription DROP CONSTRAINT FK_3A1E72959AC69B54');
        $this->addSql('ALTER TABLE account_subscription DROP CONSTRAINT FK_3A1E72959B8CE200');
        $this->addSql('ALTER TABLE account_subscription DROP CONSTRAINT FK_3A1E7295A76ED395');
        $this->addSql('DROP TABLE account_subscription_extension');
        $this->addSql('DROP TABLE account_subscription');
    }
}
