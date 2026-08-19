<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence\Doctrine\Type;

use App\VendingMachine\Domain\Machine\MachineId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * MachineId ⇄ a string column.
 *
 * The point of a custom type rather than a plain string column is that the
 * aggregate never has to hold a primitive it would then have to re-validate.
 * What comes out of the database is a MachineId or an error, and the model
 * cannot be handed anything in between.
 */
final class MachineIdType extends Type
{
    public const NAME = 'machine_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 64] + $column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        return $value instanceof MachineId
            ? $value->value()
            : throw InvalidType::new($value, self::NAME, [MachineId::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): MachineId
    {
        // Not tolerated as an empty value: this column is the primary key, so
        // a null here is not a machine without a name, it is a row that should
        // not exist.
        return \is_string($value)
            ? MachineId::fromString($value)
            : throw ValueNotConvertible::new($value, MachineId::class);
    }
}
