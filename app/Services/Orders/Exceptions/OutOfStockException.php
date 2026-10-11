<?php

namespace App\Services\Orders\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class OutOfStockException extends Exception
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Some items are no longer available in the requested quantity.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], 422);
    }
}
