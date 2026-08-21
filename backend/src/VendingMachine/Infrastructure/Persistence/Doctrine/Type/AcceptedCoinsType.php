<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence\Doctrine\Type;

use App\VendingMachine\Domain\Exception\UnsupportedCoin;
use App\VendingMachine\Domain\Money\AcceptedCoins;
use App\VendingMachine\Domain\Money\CoinDenomination;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;

/**
 * AcceptedCoins ⇄ a JSON column, `[5, 10, 25, 100]` — which denominations this
 * machine takes, in cents.
 *
 * A list and not a map, unlike the coin collections next to it in the same
 * table: this says which coins, never how many, and the column should not be
 * able to express a count nobody would read.
 *
 * An empty list is a machine switched off at the acceptor, and it round-trips
 * as `[]` rather than as null. Null would be the same story a missing column
 * tells, and those are different situations: one is a machine out of service,
 * the other is a row written before this feature existed.
 */
final class AcceptedCoinsType extends Type
{
    public const NAME = 'accepted_coins';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!$value instanceof AcceptedCoins) {
            throw InvalidType::new($value, self::NAME, [AcceptedCoins::class]);
        }

        $cents = array_map(
            static fn (CoinDenomination $denomination): int => $denomination->value,
            $value->all(),
        );

        try {
            return json_encode($cents, \JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValueNotConvertible::new($value, self::NAME, $error->getMessage(), $error);
        }
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): AcceptedCoins
    {
        if (null === $value || '' === $value) {
            return AcceptedCoins::none();
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, AcceptedCoins::class);
        }

        try {
            /** @var array<array-key, mixed> $cents */
            $cents = json_decode($value, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValueNotConvertible::new($value, AcceptedCoins::class, $error->getMessage(), $error);
        }

        return AcceptedCoins::of(...array_map(
            static function (mixed $denomination) use ($cents): CoinDenomination {
                // A denomination this build cannot read means the row was
                // written by something else, or by a later version of this
                // application. Either way it is not ours to interpret, and a
                // loud failure beats a machine that quietly takes a coin less
                // than the row says.
                //
                // UnsupportedCoin is caught rather than allowed through, and
                // that is the whole point of the catch: it is a catalogued
                // domain failure that answers 422, which would blame the caller
                // for a row they never wrote. A database we cannot read is our
                // bug, and our bugs are 500s.
                try {
                    return \is_int($denomination)
                        ? CoinDenomination::fromCents($denomination)
                        : throw ValueNotConvertible::new($cents, AcceptedCoins::class);
                } catch (UnsupportedCoin $unreadable) {
                    throw ValueNotConvertible::new($cents, AcceptedCoins::class, $unreadable->getMessage(), $unreadable);
                }
            },
            $cents,
        ));
    }
}
