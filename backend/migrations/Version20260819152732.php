<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The machine's one table.
 *
 * A migration rather than schema:create, because a deployment that builds its
 * tables from the current mapping has no way to move an existing database
 * forward — it can only ever create an empty one. This file is also the record
 * of what the schema looked like on this date, which is the part a
 * mapping-derived schema cannot give you.
 *
 * One row per machine, and everything the aggregate owns in it: the aggregate
 * is loaded and written whole, so its catalogue and its two bags of coins are
 * columns rather than tables (ADR-0008).
 */
final class Version20260819152732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the vending_machines table: one row per machine, versioned for optimistic locking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE vending_machines (
              id VARCHAR(64) NOT NULL,
              inventory CLOB NOT NULL,
              change_reserve CLOB NOT NULL,
              inserted_coins CLOB NOT NULL,
              version INTEGER DEFAULT 1 NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vending_machines');
    }
}
