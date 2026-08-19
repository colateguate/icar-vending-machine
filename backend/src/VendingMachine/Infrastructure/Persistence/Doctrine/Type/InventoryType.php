<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence\Doctrine\Type;

use App\VendingMachine\Domain\Catalog\Inventory;
use App\VendingMachine\Domain\Catalog\Product;
use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Catalog\Quantity;
use App\VendingMachine\Domain\Money\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;

/**
 * The whole catalogue ⇄ one JSON column.
 *
 * The shelves are stored inside the machine's row rather than in a products
 * table, and that is the central decision of this adapter (ADR-0008). The
 * aggregate is the transactional boundary and the port only ever asks for it
 * whole — find(MachineId) and save(), nothing else — so a second table would
 * add a join to every read, a cascade to every write, and, worse, a Doctrine
 * Collection inside the domain: an association to many needs one, and the
 * model is not allowed to name a persistence library.
 *
 * Prices travel as whole cents, the exact form the model counts in. Never as
 * JSON numbers with decimals, which come back as floats.
 */
final class InventoryType extends Type
{
    public const NAME = 'inventory';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!$value instanceof Inventory) {
            throw InvalidType::new($value, self::NAME, [Inventory::class]);
        }

        $slots = [];
        foreach ($value->all() as $product) {
            $slots[] = [
                'selector' => $product->selector()->value(),
                'name' => $product->name(),
                'priceInCents' => $product->price()->cents(),
                'available' => $product->available()->units(),
            ];
        }

        try {
            return json_encode($slots, \JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValueNotConvertible::new($value, self::NAME, $error->getMessage(), $error);
        }
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): Inventory
    {
        if (null === $value || '' === $value) {
            return Inventory::empty();
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, Inventory::class);
        }

        try {
            /** @var array<array-key, mixed> $slots */
            $slots = json_decode($value, true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValueNotConvertible::new($value, Inventory::class, $error->getMessage(), $error);
        }

        return Inventory::of(...array_map(self::productFrom(...), $slots));
    }

    /**
     * Every field is checked before it becomes a value object, because a
     * column that has been edited by hand or written by an older version of
     * this class is exactly where a corrupt aggregate would come from — and a
     * loud failure here is far cheaper than a machine that quietly sells at
     * the wrong price.
     */
    private static function productFrom(mixed $slot): Product
    {
        if (
            !\is_array($slot)
            || !\is_string($slot['selector'] ?? null)
            || !\is_string($slot['name'] ?? null)
            || !\is_int($slot['priceInCents'] ?? null)
            || !\is_int($slot['available'] ?? null)
        ) {
            throw ValueNotConvertible::new($slot, Product::class);
        }

        return new Product(
            ProductSelector::fromString($slot['selector']),
            $slot['name'],
            Money::fromCents($slot['priceInCents']),
            Quantity::of($slot['available']),
        );
    }
}
