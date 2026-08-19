<?php

declare(strict_types=1);

namespace App\Tests\Integration\VendingMachine\Infrastructure\Persistence\Doctrine\Type;

use App\VendingMachine\Domain\Machine\MachineId;
use App\VendingMachine\Infrastructure\Persistence\Doctrine\Type\MachineIdType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use PHPUnit\Framework\TestCase;

final class MachineIdTypeTest extends TestCase
{
    public function test_an_identifier_survives_the_round_trip(): void
    {
        $type = new MachineIdType();
        $platform = new SQLitePlatform();

        $stored = $type->convertToDatabaseValue(MachineId::fromString('lobby-01'), $platform);

        self::assertSame('lobby-01', $stored);
        self::assertTrue(MachineId::fromString('lobby-01')->equals($type->convertToPHPValue($stored, $platform)));
    }

    /**
     * The identifier is the primary key, so a null here is not an empty value
     * to be tolerated — it is a row that should not exist.
     */
    public function test_it_refuses_to_invent_an_identifier(): void
    {
        $this->expectException(ValueNotConvertible::class);

        (new MachineIdType())->convertToPHPValue(null, new SQLitePlatform());
    }
}
