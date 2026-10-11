<?php

namespace App\Services\Cart\Data;

/**
 * Immutable request for one quoted cart line. The client only ever sends IDs and
 * quantities; prices are always resolved on the server.
 */
final readonly class CartLineInput
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public ?int $vendorId = null,
    ) {}
}
