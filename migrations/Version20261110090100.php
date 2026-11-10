<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110090100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align the staff record owner index name with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $indexes = $this->connection->createSchemaManager()->listTableIndexes('staff_record');
        $names = array_map('strtolower', array_keys($indexes));

        // This is a no-op on fresh installs where the initial migration used the final name.
        if (in_array('idx_staff_record_created_by', $names, true) && !in_array('idx_9e492d2ab03a8386', $names, true)) {
            $this->addSql('ALTER TABLE staff_record RENAME INDEX IDX_STAFF_RECORD_CREATED_BY TO IDX_9E492D2AB03A8386');
        }
    }

    public function down(Schema $schema): void
    {
        $indexes = $this->connection->createSchemaManager()->listTableIndexes('staff_record');
        $names = array_map('strtolower', array_keys($indexes));

        if (in_array('idx_9e492d2ab03a8386', $names, true) && !in_array('idx_staff_record_created_by', $names, true)) {
            $this->addSql('ALTER TABLE staff_record RENAME INDEX IDX_9E492D2AB03A8386 TO IDX_STAFF_RECORD_CREATED_BY');
        }
    }
}
