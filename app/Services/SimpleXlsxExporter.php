<?php

namespace App\Services;

use ZipArchive;

class SimpleXlsxExporter
{
    /**
     * @param array<int, string> $headers
     * @param iterable<int, array<int, mixed>> $rows
     */
    public static function create(array $headers, iterable $rows): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_exp_');
        $sheetXmlFile = tempnam(sys_get_temp_dir(), 'sheet_xml_');

        $handle = fopen($sheetXmlFile, 'w');
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n");
        fwrite($handle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n");
        fwrite($handle, '<sheetData>' . "\n");

        $rowIndex = 1;

        // Escribir fila de cabecera
        fwrite($handle, '<row r="' . $rowIndex . '">' . "\n");
        $colIndex = 1;
        foreach ($headers as $header) {
            $colLetter = self::columnIndexToLetter($colIndex++);
            $cellRef = $colLetter . $rowIndex;
            $safeVal = htmlspecialchars((string) $header, ENT_QUOTES | ENT_XML1, 'UTF-8');
            fwrite($handle, '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $safeVal . '</t></is></c>' . "\n");
        }
        fwrite($handle, '</row>' . "\n");

        // Escribir filas de datos
        foreach ($rows as $row) {
            $rowIndex++;
            fwrite($handle, '<row r="' . $rowIndex . '">' . "\n");
            $colIndex = 1;
            foreach ($row as $cell) {
                $colLetter = self::columnIndexToLetter($colIndex++);
                $cellRef = $colLetter . $rowIndex;

                if ($cell === null || $cell === '') {
                    // Celda vacía
                    continue;
                }

                if (is_numeric($cell) && !preg_match('/^0[0-9]+/', (string) $cell)) {
                    // Valor numérico (evita ceros a la izquierda interpretados como números)
                    fwrite($handle, '<c r="' . $cellRef . '"><v>' . $cell . '</v></c>' . "\n");
                } else {
                    // Cadena de texto
                    $safeVal = htmlspecialchars((string) $cell, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    fwrite($handle, '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $safeVal . '</t></is></c>' . "\n");
                }
            }
            fwrite($handle, '</row>' . "\n");
        }

        fwrite($handle, '</sheetData>' . "\n");
        fwrite($handle, '</worksheet>');
        fclose($handle);

        // Empaquetar el ZIP (Open Packaging Conventions para XLSX)
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            unlink($sheetXmlFile);
            throw new \RuntimeException("No se pudo crear el archivo XLSX temporal.");
        }

        $zip->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '</Types>'
        );

        $zip->addFromString(
            '_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>'
        );

        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '</Relationships>'
        );

        $zip->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Productos" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>'
        );

        $zip->addFile($sheetXmlFile, 'xl/worksheets/sheet1.xml');
        $zip->close();

        unlink($sheetXmlFile);

        return $tmpFile;
    }

    private static function columnIndexToLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $modulo = ($col - 1) % 26;
            $letter = chr(65 + $modulo) . $letter;
            $col = (int) (($col - $modulo) / 26);
        }
        return $letter;
    }
}

