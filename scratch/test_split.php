<?php
$lines = file(__DIR__ . '/../storage/app/jadever_sample_layout.txt', FILE_IGNORE_NEW_LINES);

$leftCol = [];
$rightCol = [];

foreach ($lines as $line) {
    if (strlen($line) > 75) {
        $leftCol[] = rtrim(substr($line, 0, 75));
        $rightCol[] = ltrim(substr($line, 75));
    } else {
        $leftCol[] = rtrim($line);
        $rightCol[] = "";
    }
}

$text1 = implode("\n", $leftCol);
$text2 = implode("\n", $rightCol);

file_put_contents(__DIR__ . '/../storage/app/jadever_left.txt', $text1);
file_put_contents(__DIR__ . '/../storage/app/jadever_right.txt', $text2);
