<?php

declare(strict_types=1);

namespace App\Tests\Support\Reflection;

use App\VendingMachine\Delivery\Http\Error\ErrorCatalog;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * The failures this application can name, found by walking the source tree.
 *
 * Nothing here is a hand-kept list, and that is the point. Four tests ask a
 * completeness question — is every domain failure catalogued, does every
 * catalogued failure appear in the published contract, does that contract
 * promise a failure the catalog cannot produce, is the status rule stated for
 * every entry — and a completeness question checked against a list someone
 * maintained by hand only ever answers "yes, all of the ones I remembered".
 * Adding a file has to be enough to make these tests ask about it, so the
 * tests go and look.
 */
final class FailureClasses
{
    /**
     * Everything the domain marks as a failure it anticipated.
     *
     * @return list<class-string>
     */
    public static function namedByTheDomain(): array
    {
        return array_values(array_filter(
            self::classesUnder('Domain'),
            // Not isInstantiable(): the errors that carry data hide their
            // constructor behind a named one, and skipping them would quietly
            // exempt exactly the ones worth checking.
            static function (string $candidate): bool {
                $class = new ReflectionClass($candidate);

                return $class->implementsInterface(VendingMachineError::class)
                    && !$class->isInterface()
                    && !$class->isAbstract();
            },
        ));
    }

    /**
     * Everything the error catalog has an entry for — a wider set than the one
     * above, because two of the eleven are refusals of the request itself
     * (a body that is not JSON, a field of the wrong type) which the domain
     * never sees and therefore never names. That is why this one has to walk
     * the whole bounded context and not just the domain.
     *
     * Found through knows(), so it reads the catalog the way the delivery
     * layer reads it, rather than reaching into the table behind it.
     *
     * @return list<class-string>
     */
    public static function catalogued(): array
    {
        return array_values(array_filter(
            self::classesUnder('VendingMachine'),
            static fn (string $candidate): bool => ErrorCatalog::knows($candidate),
        ));
    }

    /**
     * Every class the autoloader can resolve under a named ancestor of the
     * marker interface's own directory, mapping path to name the way PSR-4
     * does.
     *
     * The ancestor is **named rather than counted**. Counting levels was the
     * first version and it hid a silent failure: move VendingMachineError one
     * directory and "two levels up" quietly becomes a different tree, so the
     * walk still finds classes, the tests still pass, and they are checking
     * the wrong scope. A gate that keeps passing while it stops guarding is
     * worse than one that fails — so when the named directory is not there,
     * this stops and says so.
     *
     * @return list<class-string>
     */
    private static function classesUnder(string $rootDirectory): array
    {
        $marker = new ReflectionClass(VendingMachineError::class);
        $fileName = $marker->getFileName();
        Assert::assertIsString($fileName, 'the marker interface has no file, so the tree cannot be walked');

        $directory = \dirname($fileName);
        $namespace = $marker->getNamespaceName();

        while (basename($directory) !== $rootDirectory) {
            $parent = \dirname($directory);

            Assert::assertNotSame($parent, $directory, \sprintf(
                'walked past the filesystem root without finding a "%s" directory above %s — the tree moved and this walk no longer means what its callers think it does',
                $rootDirectory,
                $fileName,
            ));

            $directory = $parent;
            $namespace = substr($namespace, 0, (int) strrpos($namespace, '\\'));
        }

        $classes = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($directory) + 1, -\strlen('.php'));
            $candidate = $namespace.'\\'.str_replace(\DIRECTORY_SEPARATOR, '\\', $relative);

            if (class_exists($candidate)) {
                $classes[] = $candidate;
            }
        }

        return $classes;
    }
}
