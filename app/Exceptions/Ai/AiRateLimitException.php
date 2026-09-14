<?php

namespace App\Exceptions\Ai;

class AiRateLimitException extends AiException
{
    public function __construct(string $message = "Límite de cuota o saturación temporal en el proveedor de IA.", int $code = 429, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
