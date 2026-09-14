<?php

namespace App\Exceptions\Ai;

class AiTemporaryException extends AiException
{
    public function __construct(
        string $message = "El servicio de IA no está disponible temporalmente.",
        int $code = 503,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
