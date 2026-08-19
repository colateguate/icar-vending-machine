<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Request;

use App\VendingMachine\Application\Command\PurchaseProduct\PurchaseProductCommand;
use Symfony\Component\HttpFoundation\Request;

/**
 * {"selector": "SODA"} — the button being pressed.
 *
 * The selector travels as the primitive it is. Whether "SODA" is a well-formed
 * selector is ProductSelector's question and whether this machine sells one is
 * the inventory's; they answer 422 and 404 respectively, and neither is a
 * decision this class should be making a second time.
 */
final readonly class PurchaseProductRequest
{
    private function __construct(private string $selector)
    {
    }

    public static function of(Request $request): self
    {
        return new self(JsonBody::of($request)->string('selector'));
    }

    public function toCommand(): PurchaseProductCommand
    {
        return new PurchaseProductCommand($this->selector);
    }
}
