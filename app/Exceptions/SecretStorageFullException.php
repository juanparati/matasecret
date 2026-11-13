<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class SecretStorageFullException extends Exception
{
    public function __construct()
    {
        parent::__construct('The secret storage is full. Please try again later.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 503);
    }
}
