<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110105409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message ADD submitted_by_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              message
            ADD
              CONSTRAINT FK_B6BD307F79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('CREATE INDEX IDX_B6BD307F79F7D87D ON message (submitted_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F79F7D87D');
        $this->addSql('DROP INDEX IDX_B6BD307F79F7D87D ON message');
        $this->addSql('ALTER TABLE message DROP submitted_by_id');
    }
}
