<?php

namespace App\Services\Parsers;

use App\Services\ProductParser;

class CatalogParserFactory
{
    /**
     * Determine the correct parser based on the raw catalog text.
     * 
     * @param string $text
     * @return CatalogParserInterface
     */
    public static function make(string $text): CatalogParserInterface
    {
        // Detect PROMAKER catalog
        if (stripos($text, 'PROMAKER') !== false) {
            return new PromakerParser();
        }

        if (stripos($text, 'INGCO') !== false) {
            return new IngcoParser();
        }

        if (stripos($text, 'JADEVER') !== false) {
            return new JadeverParser();
        }

        if (stripos($text, 'WADFOW') !== false) {
            return new WadfowParser();
        }

        if (stripos($text, 'VERT') !== false) {
            return new VertParser();
        }

        if (stripos($text, 'DYLLU') !== false) {
            return new DylluParser();
        }

        if (stripos($text, 'DONG CHENG') !== false || stripos($text, 'DONGCHENG') !== false) {
            return new DongChengParser();
        }

        if (stripos($text, 'RONIX') !== false) {
            return new RonixParser();
        }
        return new ProductParser();
    }
}
