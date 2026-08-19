<?php

declare(strict_types=1);

namespace App\Tests\Support\Doctrine;

use App\Kernel;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * A real SQLite database, wired exactly as production wires it.
 *
 * It boots the application's own kernel instead of assembling an
 * EntityManager by hand. Building one here would mean repeating the driver,
 * the naming strategy and the custom type registrations, and a test that
 * repeats the configuration it is meant to be checking will keep passing after
 * the real configuration breaks.
 *
 * Composition rather than a base class on purpose: the repository test extends
 * the shared port contract, and PHP has one parent to give. This is also why
 * it is a helper you hold rather than something you inherit.
 *
 * The database is a temporary file, not :memory:. Two connections to :memory:
 * are two different databases, and proving that a second writer loses needs
 * them to be looking at the same one.
 */
final class DoctrineTestEnvironment
{
    private function __construct(
        private readonly Kernel $kernel,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $databaseFile,
        private readonly ?string $previousDatabaseUrl,
    ) {
    }

    public static function boot(): self
    {
        $file = tempnam(sys_get_temp_dir(), 'vending-machine-test-');
        if (false === $file) {
            throw new RuntimeException('Could not create a temporary database file.');
        }

        // Symfony resolves %env(...)% at runtime, so putting the DSN here
        // before boot is enough to point the whole container at this file.
        // What was there before is kept: this variable is process-wide, and a
        // test that left it pointing at its own scratch file would hand the
        // next one someone else's database.
        $previous = \is_string($_SERVER['DATABASE_URL'] ?? null) ? $_SERVER['DATABASE_URL'] : null;
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///'.str_replace('\\', '/', $file);

        $kernel = new Kernel('test', true);
        $kernel->boot();

        $environment = new self($kernel, self::entityManagerOf($kernel), $file, $previous);
        $environment->createSchema();

        return $environment;
    }

    public function kernel(): Kernel
    {
        return $this->kernel;
    }

    public function entityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * The test container, for the few tests that need the composed
     * application rather than one class of it — the command bus with its
     * middleware, for instance.
     */
    public function container(): ContainerInterface
    {
        return self::containerOf($this->kernel);
    }

    /**
     * A second unit of work over the same database, which is what a second
     * request or a second worker is. Same configuration, its own connection
     * and its own identity map — otherwise both "writers" would be sharing
     * one, and the race being tested could not happen.
     */
    public function anotherEntityManager(): EntityManagerInterface
    {
        return new EntityManager(
            DriverManager::getConnection($this->entityManager->getConnection()->getParams()),
            $this->entityManager->getConfiguration(),
        );
    }

    public function shutdown(): void
    {
        $this->entityManager->getConnection()->close();
        $this->kernel->shutdown();

        if (null === $this->previousDatabaseUrl) {
            unset($_SERVER['DATABASE_URL'], $_ENV['DATABASE_URL']);
        } else {
            $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $this->previousDatabaseUrl;
        }

        @unlink($this->databaseFile);
    }

    private function createSchema(): void
    {
        Schema::createFor($this->entityManager);
    }

    private static function containerOf(Kernel $kernel): ContainerInterface
    {
        $container = $kernel->getContainer()->get('test.service_container');
        Assert::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private static function entityManagerOf(Kernel $kernel): EntityManagerInterface
    {
        $entityManager = self::containerOf($kernel)->get(EntityManagerInterface::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
