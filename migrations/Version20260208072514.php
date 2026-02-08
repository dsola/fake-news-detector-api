<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Update Article and create Verification table
 */
final class Version20260208072514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create article and verification tables with one-to-many relationship';
    }

    public function up(Schema $schema): void
    {
        // Drop the old article table
        $this->addSql('DROP TABLE IF EXISTS article CASCADE');

        // Create the new article table with UUID primary key using PostgreSQL UUID type
        $this->addSql('CREATE TABLE article (
            id UUID NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content BYTEA NOT NULL,
            verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            errored_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');

        // Create the verification table with PostgreSQL UUID type
        $this->addSql('CREATE TABLE verification (
            id UUID NOT NULL PRIMARY KEY,
            article_id UUID NOT NULL,
            type VARCHAR(50) NOT NULL,
            result VARCHAR(50) NOT NULL,
            metadata JSON NOT NULL,
            started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            terminated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            errored_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            CONSTRAINT FK_VERIFICATION_ARTICLE FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE
        )');

        // Create index on article_id for better query performance
        $this->addSql('CREATE INDEX IDX_VERIFICATION_ARTICLE ON verification (article_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop verification table
        $this->addSql('DROP TABLE IF EXISTS verification CASCADE');
        // Drop article table
        $this->addSql('DROP TABLE IF EXISTS article CASCADE');
    }
}
