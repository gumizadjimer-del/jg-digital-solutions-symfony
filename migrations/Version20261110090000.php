<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account activation state and staff-owned work records.';
    }

    public function up(Schema $schema): void
    {
        // Existing accounts remain enabled by the non-null default.
        $this->addSql('ALTER TABLE user ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');

        $this->addSql("CREATE TABLE staff_record (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(150) NOT NULL,
            description LONGTEXT NOT NULL,
            status VARCHAR(30) NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_by_id INT NOT NULL,
            INDEX IDX_9E492D2AB03A8386 (created_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE staff_record ADD CONSTRAINT FK_STAFF_RECORD_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE staff_record DROP FOREIGN KEY FK_STAFF_RECORD_CREATED_BY');
        $this->addSql('DROP TABLE staff_record');
        $this->addSql('ALTER TABLE user DROP is_active');
    }
}
