<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store Messenger-to-application record links in a separate context table.';
    }

    public function up(Schema $schema): void
    {
        // Messenger owns messenger_messages, so keep its transport schema unchanged.
        // The temporary nullable columns contain no application data: the transport never writes them.
        $this->addSql('ALTER TABLE messenger_messages DROP FOREIGN KEY FK_MESSENGER_CONTEXT_USER');
        $this->addSql('ALTER TABLE messenger_messages DROP FOREIGN KEY FK_MESSENGER_CONTEXT_REQUEST');
        $this->addSql('ALTER TABLE messenger_messages DROP FOREIGN KEY FK_MESSENGER_CONTEXT_RECORD');
        $this->addSql('ALTER TABLE messenger_messages DROP FOREIGN KEY FK_MESSENGER_CONTEXT_CONTACT');
        $this->addSql('DROP INDEX IDX_MESSENGER_CONTEXT_USER ON messenger_messages');
        $this->addSql('DROP INDEX IDX_MESSENGER_CONTEXT_REQUEST ON messenger_messages');
        $this->addSql('DROP INDEX IDX_MESSENGER_CONTEXT_RECORD ON messenger_messages');
        $this->addSql('DROP INDEX IDX_MESSENGER_CONTEXT_CONTACT ON messenger_messages');
        $this->addSql('ALTER TABLE messenger_messages DROP related_user_id, DROP related_service_request_id, DROP related_staff_record_id, DROP related_contact_message_id');

        $this->addSql('CREATE TABLE messenger_message_context (
            messenger_message_id BIGINT NOT NULL,
            user_id INT DEFAULT NULL,
            service_request_id INT DEFAULT NULL,
            staff_record_id INT DEFAULT NULL,
            contact_message_id INT DEFAULT NULL,
            INDEX IDX_MMC_USER (user_id),
            INDEX IDX_MMC_SERVICE_REQUEST (service_request_id),
            INDEX IDX_MMC_STAFF_RECORD (staff_record_id),
            INDEX IDX_MMC_CONTACT_MESSAGE (contact_message_id),
            PRIMARY KEY(messenger_message_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE messenger_message_context ADD CONSTRAINT FK_MMC_QUEUE_MESSAGE FOREIGN KEY (messenger_message_id) REFERENCES messenger_messages (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messenger_message_context ADD CONSTRAINT FK_MMC_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messenger_message_context ADD CONSTRAINT FK_MMC_SERVICE_REQUEST FOREIGN KEY (service_request_id) REFERENCES service_request (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messenger_message_context ADD CONSTRAINT FK_MMC_STAFF_RECORD FOREIGN KEY (staff_record_id) REFERENCES staff_record (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messenger_message_context ADD CONSTRAINT FK_MMC_CONTACT_MESSAGE FOREIGN KEY (contact_message_id) REFERENCES message (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_message_context');

        $this->addSql('ALTER TABLE messenger_messages ADD related_user_id INT DEFAULT NULL, ADD related_service_request_id INT DEFAULT NULL, ADD related_staff_record_id INT DEFAULT NULL, ADD related_contact_message_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_MESSENGER_CONTEXT_USER ON messenger_messages (related_user_id)');
        $this->addSql('CREATE INDEX IDX_MESSENGER_CONTEXT_REQUEST ON messenger_messages (related_service_request_id)');
        $this->addSql('CREATE INDEX IDX_MESSENGER_CONTEXT_RECORD ON messenger_messages (related_staff_record_id)');
        $this->addSql('CREATE INDEX IDX_MESSENGER_CONTEXT_CONTACT ON messenger_messages (related_contact_message_id)');
        $this->addSql('ALTER TABLE messenger_messages ADD CONSTRAINT FK_MESSENGER_CONTEXT_USER FOREIGN KEY (related_user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messenger_messages ADD CONSTRAINT FK_MESSENGER_CONTEXT_REQUEST FOREIGN KEY (related_service_request_id) REFERENCES service_request (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messenger_messages ADD CONSTRAINT FK_MESSENGER_CONTEXT_RECORD FOREIGN KEY (related_staff_record_id) REFERENCES staff_record (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messenger_messages ADD CONSTRAINT FK_MESSENGER_CONTEXT_CONTACT FOREIGN KEY (related_contact_message_id) REFERENCES message (id) ON DELETE SET NULL');
    }
}
