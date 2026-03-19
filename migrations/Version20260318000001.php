<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create client table for JWT client credentials authentication';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('client');
        $table->addColumn('id', 'uuid', ['notnull' => true]);
        $table->addColumn('client_id', Types::STRING, ['length' => 180, 'notnull' => true]);
        $table->addColumn('client_secret', Types::STRING, ['length' => 255, 'notnull' => true]);
        $table->addColumn('scopes', Types::JSON, ['notnull' => true]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['client_id'], 'UNIQ_CLIENT_ID');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('client')) {
            $schema->dropTable('client');
        }
    }
}
