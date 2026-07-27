<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store selected product variants in carts and orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart_item ADD variant_id UUID DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_cart_item_product');
        $this->addSql('CREATE INDEX idx_cart_item_variant ON cart_item (variant_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_item_product_variant ON cart_item (cart_id, product_id, variant_id)');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT fk_cart_item_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE order_item ADD variant_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_order_item_variant ON order_item (variant_id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT fk_order_item_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT fk_order_item_variant');
        $this->addSql('DROP INDEX idx_order_item_variant');
        $this->addSql('ALTER TABLE order_item DROP variant_id');

        $this->addSql('ALTER TABLE cart_item DROP CONSTRAINT fk_cart_item_variant');
        $this->addSql('DROP INDEX uniq_cart_item_product_variant');
        $this->addSql('DROP INDEX idx_cart_item_variant');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_item_product ON cart_item (cart_id, product_id)');
        $this->addSql('ALTER TABLE cart_item DROP variant_id');
    }
}
