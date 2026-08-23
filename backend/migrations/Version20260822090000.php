<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Which coins each machine takes, as a column of its own.
 *
 * Until now every machine took every denomination the acceptor could read, so
 * the answer lived in the enum and needed no column. It is state now: a
 * technician switches denominations on and off, and two machines in the same
 * lobby can disagree.
 *
 * Machines that already exist take the four the brief names. That is not a
 * neutral default — it is the behaviour they had the moment before this
 * migration ran, written down now that the acceptor knows how to read two more
 * coins. Anything else would silently change what a running machine accepts.
 */
final class Version20260822090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add accepted_coins to vending_machines, defaulting existing machines to the four coins they already took';
    }

    public function up(Schema $schema): void
    {
        // Three steps because SQLite cannot alter a column: add it nullable so
        // rows that already exist survive, fill those rows with the coins they
        // were already taking, and only then rebuild the table with the column
        // NOT NULL — which is what the mapping declares, and what
        // `make schema-check` compares against.
        //
        // The rebuild is the same temp-table dance Doctrine emits for SQLite.
        // Written out rather than left to schema:update because a migration is
        // the record of how a live database moves forward, and "run the tool
        // and hope" is not a record.
        $this->addSql('ALTER TABLE vending_machines ADD COLUMN accepted_coins CLOB DEFAULT NULL');
        $this->addSql("UPDATE vending_machines SET accepted_coins = '[5,10,25,100]' WHERE accepted_coins IS NULL");

        $this->addSql('CREATE TEMPORARY TABLE __temp__vending_machines AS SELECT id, inventory, change_reserve, inserted_coins, version, accepted_coins FROM vending_machines');
        $this->addSql('DROP TABLE vending_machines');
        $this->addSql(<<<'SQL'
            CREATE TABLE vending_machines (
              id VARCHAR(64) NOT NULL,
              inventory CLOB NOT NULL,
              change_reserve CLOB NOT NULL,
              inserted_coins CLOB NOT NULL,
              version INTEGER DEFAULT 1 NOT NULL,
              accepted_coins CLOB NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('INSERT INTO vending_machines (id, inventory, change_reserve, inserted_coins, version, accepted_coins) SELECT id, inventory, change_reserve, inserted_coins, version, accepted_coins FROM __temp__vending_machines');
        $this->addSql('DROP TABLE __temp__vending_machines');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vending_machines DROP COLUMN accepted_coins');
    }
}
