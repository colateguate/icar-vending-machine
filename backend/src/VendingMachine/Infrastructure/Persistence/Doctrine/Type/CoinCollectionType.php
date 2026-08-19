<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence\Doctrine\Type;

use App\VendingMachine\Domain\Money\CoinCollection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;

/**
 * CoinCollection ⇄ a JSON column, `{"25": 3}` — denomination in cents, and how
 * many.
 *
 * Both coin collections of the machine use this one type: the escrow and the
 * change reserve are the same concept and are told apart by the column they
 * sit in, not by having two shapes.
 *
 * Cents rather than the decimal strings the API speaks. Those exist so a
 * client cannot parse money into a float; inside the database the value is
 * only ever read by this class, and an integer is the exact form the model
 * counts in.
 */
final class CoinCollectionType extends Type
{
    public const NAME = 'coin_collection';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!$value instanceof CoinCollection) {
            throw InvalidType::new($value, self::NAME, [CoinCollection::class]);
        }

        try {
            // Forced to an object so an empty bag is "{}" and not "[]": the
            // column holds a map, and a decoder should never have to guess
            // which of the two shapes it is looking at.
            return json_encode((object) $value->toArray(), \JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValueNotConvertible::new($value, self::NAME, $error->getMessage(), $error);
        }
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): CoinCollection
    {
        if (null === $value || '' === $value) {
            return CoinCollection::empty();
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, CoinCollection::class);
        }

        try {
            /** @var array<array-key, mixed> $counts */
            $counts = json_decode($value, true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValueNotConvertible::new($value, CoinCollection::class, $error->getMessage(), $error);
        }

        return CoinCollection::fromCounts(self::wholeNumbers($counts));
    }

    /**
     * JSON has one number type and PHP has two. Reading the counts back as
     * anything but integers would give a collection that looks right and adds
     * up wrong the first time something touches it.
     *
     * The key check is ctype_digit and not is_numeric, which is the difference
     * between refusing a malformed row and silently misreading one:
     * is_numeric accepts "25.5" and "2.5e1", and casting either to int gives
     * 25 with no complaint. Nothing this class writes looks like that, so the
     * only way such a row exists is that something else wrote it — which is
     * exactly when a loud failure is worth more than a plausible number.
     *
     * @param array<array-key, mixed> $counts
     *
     * @return array<int, int>
     */
    private static function wholeNumbers(array $counts): array
    {
        $whole = [];
        foreach ($counts as $denomination => $count) {
            if (!ctype_digit((string) $denomination) || !\is_int($count)) {
                throw ValueNotConvertible::new($counts, CoinCollection::class);
            }

            $whole[(int) $denomination] = $count;
        }

        return $whole;
    }
}
