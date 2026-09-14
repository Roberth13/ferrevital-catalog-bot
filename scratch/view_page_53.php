<?php
$popplerBin = 'C:\\Tools\\poppler\\Library\\bin\\pdftotext.exe';
$pdf = 'public/PDFs/Catalogo Jadever 04-05-2026.pdf';
echo shell_exec("\"{$popplerBin}\" -f 53 -l 53 -layout \"{$pdf}\" -");
