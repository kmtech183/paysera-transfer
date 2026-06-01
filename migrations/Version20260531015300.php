<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531015300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, owner_name VARCHAR(255) NOT NULL, currency VARCHAR(3) NOT NULL, balance NUMERIC(18, 6) NOT NULL, is_active TINYINT NOT NULL, created_at VARCHAR(255) NOT NULL, updated_at VARCHAR(255) NOT NULL, version INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, idempotency_key VARCHAR(255) DEFAULT NULL, amount NUMERIC(18, 6) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(20) NOT NULL, failure_reason LONGTEXT DEFAULT NULL, created_at VARCHAR(255) NOT NULL, completed_at VARCHAR(255) DEFAULT NULL, from_account_id INT NOT NULL, to_account_id INT NOT NULL, INDEX IDX_723705D1B0CF99BD (from_account_id), INDEX IDX_723705D1BC58BDC7 (to_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1B0CF99BD FOREIGN KEY (from_account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1BC58BDC7 FOREIGN KEY (to_account_id) REFERENCES account (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1B0CF99BD');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1BC58BDC7');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE transaction');
    }
}
