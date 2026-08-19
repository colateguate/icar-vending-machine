<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence\Doctrine\Type;

use App\VendingMachine\Domain\Money\CoinCollection;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Infrastructure\Persistence\Doctrine\Type\CoinCollectionType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A bag of coins has to come out of the database as the bag that went in.
 *
 * The round trip is the whole test: a type that writes correctly and reads
 * back something subtly different — counts as strings, denominations in
 * another order — passes every assertion made against it alone and corrupts
 * the machine on the first restart.
 */
final class CoinCollectionTypeTest extends TestCase
{
    public function test_a_bag_of_coins_survives_the_round_trip(): void
    {
        $coins = CoinCollection::fromCounts([5 => 3, 25 => 1, 100 => 2]);

        self::assertTrue($coins->equals(self::roundTrip($coins)));
    }

    public function test_an_empty_bag_survives_the_round_trip(): void
    {
        self::assertTrue(self::roundTrip(CoinCollection::empty())->isEmpty());
    }

    /**
     * Counts come back as integers. JSON has one number type and PHP has two,
     * so a decoder that hands back "3" would give a collection that still
     * looks right and breaks the first time something adds to it.
     */
    public function test_counts_come_back_as_integers(): void
    {
        $restored = self::roundTrip(CoinCollection::fromCounts([10 => 4]));

        self::assertSame([10 => 4], $restored->toArray());
        self::assertSame(4, $restored->countOf(CoinDenomination::TEN_CENTS));
    }

    public function test_it_stores_something_a_human_can_read(): void
    {
        $stored = (new CoinCollectionType())->convertToDatabaseValue(
            CoinCollection::fromCounts([25 => 2]),
            new SQLitePlatform(),
        );

        self::assertSame('{"25":2}', $stored);
    }

    public function test_a_missing_column_is_an_empty_bag(): void
    {
        self::assertTrue(
            (new CoinCollectionType())->convertToPHPValue(null, new SQLitePlatform())->isEmpty(),
        );
    }

    /**
     * A denomination that is not a whole number of cents did not come from
     * here, so the only interesting question is whether it is refused or
     * quietly misread. "25.5" and "2.5e1" both survive is_numeric and both
     * become 25 when cast, which would turn a corrupt row into a plausible
     * one — the worst of the two outcomes.
     *
     * @param non-empty-string $stored
     */
    #[DataProvider('rowsNoOneShouldTrust')]
    public function test_it_refuses_a_denomination_that_is_not_whole_cents(string $stored): void
    {
        $this->expectException(ValueNotConvertible::class);

        (new CoinCollectionType())->convertToPHPValue($stored, new SQLitePlatform());
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function rowsNoOneShouldTrust(): iterable
    {
        yield 'a denomination with decimals' => ['{"25.5":2}'];
        yield 'a denomination in scientific notation' => ['{"2.5e1":2}'];
        yield 'a negative denomination' => ['{"-25":2}'];
        yield 'a count that is not a whole number' => ['{"25":2.5}'];
        yield 'a count that is a string' => ['{"25":"2"}'];
    }

    private static function roundTrip(CoinCollection $coins): CoinCollection
    {
        $type = new CoinCollectionType();
        $platform = new SQLitePlatform();

        return $type->convertToPHPValue($type->convertToDatabaseValue($coins, $platform), $platform);
    }
}
