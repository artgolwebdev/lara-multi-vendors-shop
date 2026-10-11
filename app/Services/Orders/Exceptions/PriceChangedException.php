<?php

namespace App\Services\Orders\Exceptions;

use App\Services\Cart\Data\PriceQuote;
use Exception;
use Illuminate\Http\JsonResponse;

class PriceChangedException extends Exception
{
    public function __construct(private readonly PriceQuote $quote)
    {
        parent::__construct('Some prices or discounts changed since you reviewed your order.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'data' => $this->quote->toArray(),
        ], 409);
    }
}
