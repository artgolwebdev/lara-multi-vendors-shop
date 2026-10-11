<?php

namespace App\Services\Orders\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class IdempotencyConflictException extends Exception
{
    public function __construct()
    {
        parent::__construct('This idempotency key is already in use.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
