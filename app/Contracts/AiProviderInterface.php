<?php

namespace App\Contracts;

use App\Exceptions\Ai\AiException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiResponseParseException;

interface AiProviderInterface
{
    /**
     * Extrae productos de un fragmento de texto utilizando el proveedor de IA configurado.
     *
     * @param string $chunkText
     * @return array
     * @throws AiRateLimitException
     * @throws AiResponseParseException
     * @throws AiException
     */
    public function extractProductsFromChunk(string $chunkText): array;

    /**
     * Reordena una lista de productos en base a su relación calidad-precio según la IA.
     *
     * @param array $productsData
     * @param string $searchQuery
     * @return array Array de IDs reordenados por la IA
     * @throws AiException
     */
    public function rankProductsByValueForMoney(array $productsData, string $searchQuery): array;

    /**
     * Nombre identificador del proveedor (ej. 'gemini', 'openai')
     */
    public function getProviderName(): string;

    /**
     * Nombre del modelo (ej. 'gemini-3.6-flash')
     */
    public function getModelName(): string;

    /**
     * Versión del prompt de extracción utilizado
     */
    public function getPromptVersion(): string;
}
