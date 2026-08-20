<?php

declare(strict_types=1);

namespace App\Tests\Unit\VendingMachine\Domain\Catalog;

use App\VendingMachine\Domain\Catalog\ProductSelector;
use App\VendingMachine\Domain\Exception\InvalidProductSelector;
use App\VendingMachine\Domain\Exception\VendingMachineError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductSelectorTest extends TestCase
{
    public function test_it_carries_the_button_label(): void
    {
        self::assertSame('WATER', ProductSelector::fromString('WATER')->value());
    }

    #[DataProvider('acceptedSelectors')]
    public function test_it_accepts_a_well_formed_selector(string $value): void
    {
        self::assertSame($value, ProductSelector::fromString($value)->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedSelectors(): iterable
    {
        yield 'water' => ['WATER'];
        yield 'juice' => ['JUICE'];
        yield 'soda' => ['SODA'];
        yield 'single letter' => ['A'];
        yield 'underscore separated' => ['SPARKLING_WATER'];
        yield 'hyphen separated' => ['COLA-ZERO'];
        yield 'with a digit' => ['COLA7'];
        yield 'longest accepted' => [str_repeat('A', 32)];
    }

    #[DataProvider('rejectedSelectors')]
    public function test_it_rejects_a_malformed_selector(string $value): void
    {
        $this->expectException(InvalidProductSelector::class);

        ProductSelector::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedSelectors(): iterable
    {
        yield 'empty' => [''];
        yield 'lowercase' => ['water'];
        yield 'mixed case' => ['Water'];
        yield 'inner space' => ['SPARKLING WATER'];
        yield 'surrounding space' => [' WATER '];
        yield 'punctuation' => ['WATER!'];
        yield 'starts with a digit' => ['7UP'];
        yield 'starts with a separator' => ['_WATER'];
        yield 'one over the limit' => [str_repeat('A', 33)];
    }

    public function test_a_malformed_selector_is_a_vending_machine_error(): void
    {
        $this->expectException(VendingMachineError::class);

        ProductSelector::fromString('nope');
    }

    public function test_the_rejected_value_is_reported_in_the_message(): void
    {
        $this->expectExceptionMessage('water');

        ProductSelector::fromString('water');
    }

    public function test_selectors_with_the_same_label_are_equal(): void
    {
        self::assertTrue(ProductSelector::fromString('SODA')->equals(ProductSelector::fromString('SODA')));
        self::assertFalse(ProductSelector::fromString('SODA')->equals(ProductSelector::fromString('JUICE')));
    }
}
