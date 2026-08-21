<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence\Doctrine\Type;

use App\VendingMachine\Domain\Money\AcceptedCoins;
use App\VendingMachine\Domain\Money\CoinDenomination;
use App\VendingMachine\Infrastructure\Persistence\Doctrine\Type\AcceptedCoinsType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Which coins a machine takes has to come out of the database as what went in.
 * A machine that quietly comes back taking one denomination fewer than it was
 * left with is money it will refuse and change it will not give.
 */
final class AcceptedCoinsTypeTest extends TestCase
{
    public function test_a_set_of_coins_survives_the_round_trip(): void
    {
        $accepted = AcceptedCoins::of(
            CoinDenomination::FIVE_CENTS,
            CoinDenomination::FIFTY_CENTS,
            CoinDenomination::TWO_UNITS,
        );

        self::assertTrue($accepted->equals(self::roundTrip($accepted)));
    }

    /**
     * The state that says the machine is switched off, and the one most easily
     * lost in a round trip: an empty set has to come back empty rather than as
     * "no answer stored, so take everything".
     */
    public function test_a_machine_that_takes_nothing_survives_the_round_trip(): void
    {
        self::assertTrue(self::roundTrip(AcceptedCoins::none())->isEmpty());
    }

    public function test_it_stores_something_a_human_can_read(): void
    {
        $stored = (new AcceptedCoinsType())->convertToDatabaseValue(
            AcceptedCoins::of(CoinDenomination::TEN_CENTS, CoinDenomination::ONE_UNIT),
            new SQLitePlatform(),
        );

        self::assertSame('[10,100]', $stored);
    }

    public function test_a_column_written_before_this_feature_existed_takes_nothing(): void
    {
        self::assertTrue(
            (new AcceptedCoinsType())->convertToPHPValue(null, new SQLitePlatform())->isEmpty(),
        );
    }

    /**
     * A row this build cannot read is our problem, and it has to arrive as one.
     *
     * The interesting case is the third: a denomination that is a whole number
     * of cents and not one the acceptor knows makes CoinDenomination throw
     * UnsupportedCoin, which the error catalog answers with a 422 — a status
     * that blames the caller for a row they never wrote. Wrapping it is what
     * keeps a corrupt database a 500.
     *
     * @param non-empty-string $stored
     */
    #[DataProvider('rowsNoOneShouldTrust')]
    public function test_it_refuses_a_row_it_cannot_read(string $stored): void
    {
        $this->expectException(ValueNotConvertible::class);

        (new AcceptedCoinsType())->convertToPHPValue($stored, new SQLitePlatform());
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function rowsNoOneShouldTrust(): iterable
    {
        yield 'not JSON at all' => ['5,10,25'];
        yield 'denominations as strings' => ['["5","10"]'];
        yield 'a denomination the acceptor cannot read' => ['[5,3]'];
        yield 'a denomination with decimals' => ['[5.5]'];
    }

    private static function roundTrip(AcceptedCoins $accepted): AcceptedCoins
    {
        $type = new AcceptedCoinsType();
        $platform = new SQLitePlatform();

        return $type->convertToPHPValue($type->convertToDatabaseValue($accepted, $platform), $platform);
    }
}
