<?php

namespace Omniplay\Model;

/** A price in one country, in minor units: 969 EUR is 9.69 €. */
final readonly class Price
{
    /** @param ?int $initial the price before the discount, when there is one */
    public function __construct(public int $amount, public string $currency, public ?int $initial = null, public int $discount = 0)
    {
    }

    public function isFree(): bool
    {
        return 0 === $this->amount;
    }

    /** @return array{amount: int, currency: string, initial: ?int, discount: int} */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency, 'initial' => $this->initial, 'discount' => $this->discount];
    }
}
