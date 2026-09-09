<?php

namespace App\Services\Parsers;

interface CatalogParserInterface
{
    /**
     * Parse the given text (or multiple texts like normalText and redText)
     * and extract a list of products.
     *
     * @param string $normalText
     * @param string $redText
     * @param int|null $catalogId
     * @return array
     */
    public function parse(string $normalText, string $redText = '', ?int $catalogId = null): array;
}
