<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow boutique filter options to exist independently from products.';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $this->addSql('ALTER TABLE product_filter_value ALTER COLUMN product_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        $this->addSql('DELETE FROM product_filter_value WHERE product_id IS NULL');
        $this->addSql('ALTER TABLE product_filter_value ALTER COLUMN product_id SET NOT NULL');
    }
}
