<?php

namespace App\Domain\Biometria;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class BiometriaException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
