<?php
$files = glob('app/Services/Parsers/*.php');
$files[] = 'app/Services/ProductParser.php';

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $c = file_get_contents($file);
    if (strpos($c, 'public function parse(string $normalText, string $redText = \'\'): array') !== false) {
        $c = str_replace(
            'public function parse(string $normalText, string $redText = \'\'): array',
            'public function parse(string $normalText, string $redText = \'\', ?int $catalogId = null): array',
            $c
        );
        file_put_contents($file, $c);
        echo "Updated $file\n";
    }
}
