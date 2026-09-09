<?php

namespace App\Services;

class ColorTextExtractor
{
    public function extractRed(string $imagePath): string
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('La extensión GD de PHP no está instalada.');
        }

        if (!file_exists($imagePath)) {
            throw new \RuntimeException("Imagen no encontrada: {$imagePath}");
        }

        $image = imagecreatefrompng($imagePath);

        if ($image === false) {
            throw new \RuntimeException("No se pudo abrir la imagen: {$imagePath}");
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $output = imagecreatetruecolor($width, $height);

        $white = imagecolorallocate($output, 255, 255, 255);
        $black = imagecolorallocate($output, 0, 0, 0);

        imagefill($output, 0, 0, $white);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);

                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $isRed =
                    $r > 120 &&
                    $r > $g * 1.4 &&
                    $r > $b * 1.4;

                if ($isRed) {
                    imagesetpixel($output, $x, $y, $black);
                }
            }
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'red_') . '.png';

        imagepng($output, $temporaryPath);

        imagedestroy($image);
        imagedestroy($output);

        return $temporaryPath;
    }
}