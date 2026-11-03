<?php

namespace Tualo\Office\Zugferd;

class FacturX
{
    public function __construct(
        private string $mustangJar,
        private string $iccProfile,
        private string $javaBinary = 'java',
        private string $ghostscriptBinary = 'gs'
    ) {
        foreach ([$mustangJar, $iccProfile] as $file) {
            if (!is_file($file) || !is_readable($file)) {
                throw new \RuntimeException('ZUGFeRD: Datei fehlt oder ist nicht lesbar: ' . $file);
            }
        }
        $this->mustangJar = realpath($mustangJar);
        $this->iccProfile = realpath($iccProfile);
    }

    public function validateXml(string $xml): void
    {
        $directory = $this->createDirectory();
        try {
            $this->write($directory . '/factur-x.xml', $xml);
            $this->validateFile($directory . '/factur-x.xml', $directory);
        } finally {
            $this->cleanup($directory);
        }
    }

    public function visualize(string $xml, string $language = 'de'): string
    {
        if (!in_array($language, ['de', 'en', 'fr'], true)) {
            throw new \RuntimeException('ZUGFeRD: Unbekannte Sprache für die HTML-Ansicht: ' . $language);
        }
        $directory = $this->createDirectory();
        try {
            $this->write($directory . '/factur-x.xml', $xml);
            // Mustang schreibt xrechnung-viewer.css/js zusätzlich ins Arbeitsverzeichnis;
            // die HTML-Ausgabe selbst ist self-contained, cleanup() entfernt die Kopien.
            $this->run([
                $this->javaBinary, '-jar', $this->mustangJar,
                '--action', 'visualize', '--language', $language,
                '--source', $directory . '/factur-x.xml',
                '--out', $directory . '/invoice.html',
                '--disable-file-logging',
            ], $directory);
            $html = file_get_contents($directory . '/invoice.html');
            if ($html === false || trim($html) === '') {
                throw new \RuntimeException('ZUGFeRD: Keine HTML-Ausgabe erzeugt.');
            }
            return $html;
        } finally {
            $this->cleanup($directory);
        }
    }

    public function create(string $sourcePdf, string $xml): string
    {
        if (!is_file($sourcePdf) || !is_readable($sourcePdf)) {
            throw new \RuntimeException('ZUGFeRD: Quell-PDF fehlt oder ist nicht lesbar.');
        }
        $sourcePdf = realpath($sourcePdf);
        $directory = $this->createDirectory();
        try {
            $this->write($directory . '/factur-x.xml', $xml);
            $this->validateFile($directory . '/factur-x.xml', $directory);
            $profile = file_get_contents($this->iccProfile);
            if ($profile === false) {
                throw new \RuntimeException('ZUGFeRD: ICC-Profil konnte nicht gelesen werden.');
            }
            $this->write($directory . '/profile.icc', $profile);
            $this->write($directory . '/pdfa.ps', <<<'PS'
%!
/ICCProfile (profile.icc) def
[/_objdef {icc_PDFA} /type /stream /OBJ pdfmark
[{icc_PDFA} << /N 3 >> /PUT pdfmark
[{icc_PDFA} ICCProfile (r) file /PUT pdfmark
[/_objdef {OutputIntent_PDFA} /type /dict /OBJ pdfmark
[{OutputIntent_PDFA} <<
    /Type /OutputIntent
    /S /GTS_PDFA1
    /DestOutputProfile {icc_PDFA}
    /OutputConditionIdentifier (sRGB)
>> /PUT pdfmark
[{Catalog} << /OutputIntents [ {OutputIntent_PDFA} ] >> /PUT pdfmark
PS);
            $this->run([
                $this->ghostscriptBinary, '-dBATCH', '-dNOPAUSE', '-dSAFER',
                '--permit-file-read=profile.icc',
                '-sDEVICE=pdfwrite', '-dPDFA=3', '-dPDFACompatibilityPolicy=1',
                '-sColorConversionStrategy=RGB', '-dEmbedAllFonts=true',
                '-sOutputFile=' . $directory . '/pdfa.pdf',
                $directory . '/pdfa.ps', $sourcePdf,
            ], $directory);
            $this->run([
                $this->javaBinary, '-jar', $this->mustangJar,
                '--action', 'combine', '--source', $directory . '/pdfa.pdf',
                '--source-xml', $directory . '/factur-x.xml',
                '--out', $directory . '/invoice.pdf',
                '--format', 'fx', '--version', '2', '--profile', 'E',
                '--no-additional-attachments', '--disable-file-logging',
            ], $directory);
            $this->validateFile($directory . '/invoice.pdf', $directory);
            $pdf = file_get_contents($directory . '/invoice.pdf');
            if ($pdf === false || !str_starts_with($pdf, '%PDF-')) {
                throw new \RuntimeException('ZUGFeRD: Keine gültige PDF-Ausgabe erzeugt.');
            }
            return $pdf;
        } finally {
            $this->cleanup($directory);
        }
    }

    private function validateFile(string $file, string $directory): void
    {
        [$status, $output] = $this->execute([
            $this->javaBinary, '-jar', $this->mustangJar,
            '--action', 'validate', '--source', $file,
            '--no-notices', '--disable-file-logging',
        ], $directory);
        $start = strpos($output, '<validation ');
        $end = strrpos($output, '</validation>');
        if ($start === false || $end === false) {
            throw new \RuntimeException('ZUGFeRD: Kein Mustang-Prüfbericht: ' . $output);
        }
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML(substr($output, $start, $end + strlen('</validation>') - $start), LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            throw new \RuntimeException('ZUGFeRD: Mustang-Prüfbericht ist nicht lesbar.');
        }
        $xpath = new \DOMXPath($document);
        if ($status !== 0 || $xpath->evaluate('string(/validation/summary/@status)') !== 'valid') {
            $errors = [];
            foreach ($xpath->query('//error | //exception') as $error) {
                $errors[] = trim($error->textContent);
            }
            $details = $errors === [] ? 'Mustang-Status ' . $status . ': ' . $output : implode("\n", array_unique($errors));
            throw new \RuntimeException('Ungültige ZUGFeRD-Rechnung: ' . $details);
        }
    }

    private function run(array $command, string $directory): void
    {
        [$status, $output] = $this->execute($command, $directory);
        if ($status !== 0) {
            throw new \RuntimeException('ZUGFeRD: ' . $command[0] . ' fehlgeschlagen: ' . $output);
        }
    }

    private function execute(array $command, string $directory): array
    {
        $log = $directory . '/command.log';
        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $log, 'w'],
            2 => ['file', $log, 'a'],
        ], $pipes, $directory);
        if (!is_resource($process)) {
            throw new \RuntimeException('ZUGFeRD: Prozess konnte nicht gestartet werden: ' . $command[0]);
        }
        $status = proc_close($process);
        $output = file_get_contents($log);
        if ($output === false) {
            throw new \RuntimeException('ZUGFeRD: Prozessausgabe konnte nicht gelesen werden.');
        }
        return [$status, $output];
    }

    private function createDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/factur-x-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new \RuntimeException('ZUGFeRD: Temporäres Verzeichnis konnte nicht erstellt werden.');
        }
        return $directory;
    }

    private function write(string $file, string $content): void
    {
        if (file_put_contents($file, $content) !== strlen($content)) {
            throw new \RuntimeException('ZUGFeRD: Datei konnte nicht geschrieben werden: ' . $file);
        }
    }

    private function cleanup(string $directory): void
    {
        foreach (new \DirectoryIterator($directory) as $file) {
            if (!$file->isDot()) {
                if (!$file->isFile() || !unlink($file->getPathname())) {
                    throw new \RuntimeException('ZUGFeRD: Temporäre Datei konnte nicht entfernt werden.');
                }
            }
        }
        if (!rmdir($directory)) {
            throw new \RuntimeException('ZUGFeRD: Temporäres Verzeichnis konnte nicht entfernt werden.');
        }
    }
}
