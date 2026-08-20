<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Request;

use App\VendingMachine\Application\Command\InsertCoin\InsertCoinCommand;
use App\VendingMachine\Domain\Money\Money;
use Symfony\Component\HttpFoundation\Request;

/**
 * {"coin": "0.25"}.
 *
 * A decimal string and not a JSON number: a number would be a float on both
 * sides of the wire, which is the trap the whole money model exists to avoid.
 * Whether that string is an amount at all is Money's question, and whether the
 * amount is a coin this machine takes is CoinDenomination's — this class only
 * insists that a string arrived where a string was asked for.
 */
final readonly class InsertCoinRequest
{
    private function __construct(private string $coin)
    {
    }

    public static function of(Request $request): self
    {
        return new self(JsonBody::of($request)->string('coin'));
    }

    public function toCommand(): InsertCoinCommand
    {
        return new InsertCoinCommand(Money::fromDecimalString($this->coin)->cents());
    }
}
