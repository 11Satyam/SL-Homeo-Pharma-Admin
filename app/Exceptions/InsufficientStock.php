<?php

namespace App\Exceptions;

use RuntimeException;

/** An order asks for more than the distributor has free. Lists every short product, not just the first. */
class InsufficientStock extends RuntimeException
{
    /** @param list<array{product_id:int, product:string, ordered:int, available:int}> $shortages */
    public function __construct(public readonly array $shortages)
    {
        $parts = array_map(fn ($s) => "{$s['product']} (ordered {$s['ordered']}, ".($s['available'] > 0 ? "only {$s['available']} in stock" : 'none in stock').')', $shortages);

        parent::__construct('Not enough stock for '.implode(', ', $parts).'. '
            .(count($shortages) === 1 ? 'Lower the quantity' : 'Lower these quantities').' or remove the item, or get more stock transferred to this distributor first.');
    }
}
