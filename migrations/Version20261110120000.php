<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional foreign-key context from queued Messenger messages to application records.';
    }

    public function up(Schema $schema): void
    {
        // Nullable context columns keep Symfony Messenger able to enqueue generic jobs.
        $this->addSql('ALTER TABLE messenger_messages ADD related_user_id INT DEFAULT NULL, ADD related_service_request_id INT DEFAULT NULL, ADD related_staff_record_id INT DEFAULT NULL, ADD related_contact_message_id INT DEFAULT NULL');

        $this->addSql('CREATE INDEX IDX_MESSENGER_CONTEXT_USER ON messenger_messages (related_user_id)');
        $this->addSql('CREATE INDEX IDX_MESSENGER_CONTEXT_REQUEST ON messenger_messages (related_service_request_id)');
        $this->addSql('CREATE INDEX IDX_MESSENGER_CONTEXT_RECORD ON messenger_messages (related_staff_record_id)');
        $this->addSql('CREATE INDEX IDX_MESSENGER_CONTEXT_CONTACT ON messenger_messages (related_contact_message_id)');

        // If a related business row is removed, keep the queued job and clear only its reference.
        $this->addSql('ALTER TABLE messenger_messages ADD CONSTRAINT FK_MESSENGER_CONTEXT_USER FOREIGN KEY (related_user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messenger_messages ADD CONSTRAINT FK_MESSENGER_CONTEXT_REQUEST FOREIGN KEY (related_service_request_id) REFERENCES service_request (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messenger_messages ADD CONSTRAINT FK_MESSENGER_CONTEXT_RECORD FOREIGN KEY (related_staff_record_id) REFERENCES staff_record (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messenger_messages ADD CONSTRAINT FK_MESSENGER_CONTEXT_CONTACT FOREIGN KEY (related_contact_message_id) REFERENCES message (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messenger_messages DROP FOREIGN KEY FK_MESSENGER_CONTEXT_USER');
        $this->addSql('ALTER TABLE messenger_messages DROP FOREIGN KEY FK_MESSENGER_CONTEXT_REQUEST');
        $this->addSql('ALTER TABLE messenger_messages DROP FOREIGN KEY FK_MESSENGER_CONTEXT_RECORD');
        $this->addSql('ALTER TABLE messenger_messages DROP FOREIGN KEY FK_MESSENGER_CONTEXT_CONTACT');

        $this->addSql('DROP INDEX IDX_MESSENGER_CONTEXT_USER ON messenger_messages');
        $this->addSql('DROP INDEX IDX_MESSENGER_CONTEXT_REQUEST ON messenger_messages');
        $this->addSql('DROP INDEX IDX_MESSENGER_CONTEXT_RECORD ON messenger_messages');
        $this->addSql('DROP INDEX IDX_MESSENGER_CONTEXT_CONTACT ON messenger_messages');

        $this->addSql('ALTER TABLE messenger_messages DROP related_user_id, DROP related_service_request_id, DROP related_staff_record_id, DROP related_contact_message_id');
    }
}
