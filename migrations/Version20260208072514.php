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
        // Drop the old article table if it exists
        if ($schema->hasTable('article')) {
            $schema->dropTable('article');
        }

        // Create the article table
        $articleTable = $schema->createTable('article');
        $articleTable->addColumn('id', 'uuid', ['notnull' => true]);
        $articleTable->addColumn('title', 'string', ['length' => 255, 'notnull' => true]);
        $articleTable->addColumn('content', 'text', ['notnull' => false]);
        $articleTable->addColumn('url', 'string', ['length' => 255, 'notnull' => true]);
        $articleTable->addColumn('verified_at', 'datetime', ['notnull' => false]);
        $articleTable->addColumn('errored_at', 'datetime', ['notnull' => false]);
        $articleTable->addColumn('created_at', 'datetime', ['notnull' => true]);
        $articleTable->addColumn('updated_at', 'datetime', ['notnull' => true]);
        $articleTable->setPrimaryKey(['id']);

        // Create the verification table
        $verificationTable = $schema->createTable('verification');
        $verificationTable->addColumn('id', 'uuid', ['notnull' => true]);
        $verificationTable->addColumn('article_id', 'uuid', ['notnull' => true]);
        $verificationTable->addColumn('type', 'string', ['length' => 50, 'notnull' => true]);
        $verificationTable->addColumn('result', 'string', ['length' => 50, 'notnull' => true]);
        $verificationTable->addColumn('metadata', 'json', ['notnull' => true]);
        $verificationTable->addColumn('started_at', 'datetime', ['notnull' => false]);
        $verificationTable->addColumn('terminated_at', 'datetime', ['notnull' => false]);
        $verificationTable->addColumn('errored_at', 'datetime', ['notnull' => false]);
        $verificationTable->addColumn('created_at', 'datetime', ['notnull' => true]);
        $verificationTable->setPrimaryKey(['id']);
        $verificationTable->addForeignKeyConstraint('article', ['article_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_VERIFICATION_ARTICLE');
        $verificationTable->addIndex(['article_id'], 'IDX_VERIFICATION_ARTICLE');
    }

    public function down(Schema $schema): void
    {
        // Drop verification table
        if ($schema->hasTable('verification')) {
            $schema->dropTable('verification');
        }

        // Drop article table
        if ($schema->hasTable('article')) {
            $schema->dropTable('article');
        }
    }
}
