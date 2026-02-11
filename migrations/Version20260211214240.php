<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211214240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add similar_article table to store verified similar articles';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('similar_article');
        $table->addColumn('id', 'uuid', ['notnull' => true]);
        $table->addColumn('article_id', 'uuid', ['notnull' => true]);
        $table->addColumn('source', Types::STRING, ['length' => 255, 'notnull' => true]);
        $table->addColumn('author', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => true]);
        $table->addColumn('content', Types::TEXT, ['notnull' => false]);
        $table->addColumn('score', Types::FLOAT, ['notnull' => true]);
        $table->addColumn('url', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('published_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);
        
        $table->setPrimaryKey(['id']);
        
        $table->addIndex(['article_id'], 'idx_similar_article_article_id');
        
        $table->addForeignKeyConstraint(
            'article',
            ['article_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_similar_article_article_id'
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('similar_article');
    }
}

