<?php

namespace App\Exceptions;

use Exception;

class CatalogParseException extends Exception
{
    /**
     * @param string $message
     * @param string|null $rawPayload
     */
    public function __construct(string $message, public readonly ?string $rawPayload = null)
    {
        parent::__construct($message);
    }
}
