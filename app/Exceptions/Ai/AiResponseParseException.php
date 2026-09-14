<?php

namespace App\Exceptions\Ai;

class AiResponseParseException extends AiException
{
    public function __construct(
        string $message = "La respuesta devuelta por el proveedor de IA no tiene la estructura esperada.",
        public readonly string $rawResponse = "",
        int $code = 0,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
