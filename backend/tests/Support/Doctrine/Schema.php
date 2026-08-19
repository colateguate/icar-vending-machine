<?php

declare(strict_types=1);

namespace App\Tests\Support\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;

/**
 * Builds the tables a test needs, from the mapping the application actually
 * uses.
 *
 * From the mapping and not from the migration on purpose. Running the
 * migration here would prove the migration is self-consistent and nothing
 * else, while this fails the moment the XML says something the code does not.
 * Whether the migration still matches the mapping is a different question, and
 * `doctrine:schema:validate` in the quality gate is what asks it.
 *
 * Nothing tears it down: the tests that use this run against a database that
 * only lives as long as its connection, so cleanup is the connection closing.
 */
final class Schema
{
    public static function createFor(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        Assert::assertNotEmpty($metadata, 'no mapping was found — the XML driver is not seeing config/doctrine');

        (new SchemaTool($entityManager))->createSchema($metadata);
    }

    public static function createForContainer(ContainerInterface $container): void
    {
        $entityManager = $container->get(EntityManagerInterface::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        self::createFor($entityManager);
    }
}
