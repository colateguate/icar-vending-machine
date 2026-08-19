<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Machine;

use App\VendingMachine\Domain\Machine\MachineId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MachineIdTest extends TestCase
{
    public function test_it_carries_the_identifier(): void
    {
        self::assertSame('lobby-01', MachineId::fromString('lobby-01')->value());
    }

    #[DataProvider('rejectedIdentifiers')]
    public function test_it_rejects_an_unusable_identifier(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        MachineId::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'only whitespace' => ['   '];
        yield 'longer than the limit' => [str_repeat('a', 65)];
    }

    public function test_it_accepts_the_longest_usable_identifier(): void
    {
        $longest = str_repeat('a', 64);

        self::assertSame($longest, MachineId::fromString($longest)->value());
    }

    public function test_identifiers_with_the_same_value_are_equal(): void
    {
        self::assertTrue(MachineId::fromString('lobby-01')->equals(MachineId::fromString('lobby-01')));
        self::assertFalse(MachineId::fromString('lobby-01')->equals(MachineId::fromString('lobby-02')));
    }

    /**
     * Anything that keys a map by identity needs the identity to spell itself,
     * and a persistence adapter's identity map is one of those. Asserted here
     * rather than left to the adapter's tests, because if this ever stopped
     * being the plain value the failure would surface as machines quietly
     * overwriting each other.
     */
    public function test_it_spells_itself_as_its_own_value(): void
    {
        self::assertSame('lobby-01', (string) MachineId::fromString('lobby-01'));
    }
}
