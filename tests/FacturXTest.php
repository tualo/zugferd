<?php

namespace Tualo\Office\Report {
    class Report
    {
        public static array $data;

        public static function get(string $type, int $id): array
        {
            return self::$data;
        }
    }
}

namespace {
    // Dependency-only bootstrap: the package's functions.php needs a running Tualo app.
    $vendor = dirname(__DIR__) . '/vendor';
    require $vendor . '/composer/ClassLoader.php';
    $loader = new \Composer\Autoload\ClassLoader();
    foreach (require $vendor . '/composer/autoload_psr4.php' as $prefix => $paths) {
        $loader->addPsr4($prefix, $paths);
    }
    $loader->addClassMap(require $vendor . '/composer/autoload_classmap.php');
    $loader->register();
    foreach (require $vendor . '/composer/autoload_files.php' as $file) {
        if (realpath($file) !== realpath(dirname(__DIR__) . '/src/functions.php')) {
            require_once $file;
        }
    }
    require dirname(__DIR__) . '/src/Report.php';
    require dirname(__DIR__) . '/src/FacturX.php';

    use Easybill\ZUGFeRD2\Builder;
    use Tualo\Office\Zugferd\FacturX;
    use Tualo\Office\Zugferd\Report;

    if ($argc !== 5) {
        fwrite(STDERR, "Usage: php tests/FacturXTest.php mustang.jar srgb.icc source.pdf output.pdf\n");
        exit(1);
    }

    \Tualo\Office\Report\Report::$data = [
        'id' => 1, 'date' => '2026-10-31', 'reference' => 'TEST',
        'net' => 100, 'steuer' => 19, 'gross' => 119, 'open' => 119,
        'seller_information' => [
            'line1' => 'Test Seller', 'line3' => 'Teststrasse 1',
            'city' => 'Berlin', 'postcode' => '10115', 'country' => 'DE',
        ],
        'buyer_information' => [
            'line1' => 'Test Buyer', 'line3' => 'Teststrasse 2',
            'city' => 'Berlin', 'postcode' => '10115', 'country' => 'DE',
        ],
        'tax_registration' => ['VA' => 'DE123456789'],
        'positions' => [[
            'position' => 1, 'artikel_text' => 'Testartikel', 'article' => 'TEST',
            'singleprice' => 100, 'amount' => 1, 'einheit_symbol' => 'C62',
            'tax' => 19, 'net' => 100,
        ]],
        'taxes' => [['net' => 100, 'tax' => 19, 'rate' => 19]],
    ];
    $converter = new FacturX($argv[1], $argv[2]);
    $profile = Builder::GUIDELINE_SPECIFIED_DOCUMENT_CONTEXT_ID_EN16931;
    $xml = Report::get('test', 1, $profile);
    if (!str_contains($xml, $profile) || str_contains($xml, 'TaxCurrencyCode')) {
        throw new \RuntimeException('EN16931-Profil oder Steuerwährung falsch.');
    }
    $schemaError = (new \Easybill\ZUGFeRD2\Validator())->validateAgainstXsd(
        $xml,
        \Easybill\ZUGFeRD2\Validator::SCHEMA_EN16931
    );
    if ($schemaError !== null) {
        throw new \RuntimeException($schemaError);
    }
    $converter->validateXml($xml);
    echo "PASS: EN16931-XML valid\n";
    unset(\Tualo\Office\Report\Report::$data['tax_registration']);
    try {
        $converter->create($argv[3], Report::get('test', 1, $profile));
        throw new \LogicException('Ungültiges XML wurde nicht abgelehnt.');
    } catch (\RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'BR-S-02')) {
            throw $exception;
        }
        echo "PASS: Fehlende Steuerkennung wird abgelehnt\n";
    }
    if (!str_contains(Report::get('test', 1), Builder::GUIDELINE_SPECIFIED_DOCUMENT_CONTEXT_ID_XRECHNUNG)) {
        throw new \RuntimeException('Bestehendes XML-Standardprofil wurde verändert.');
    }
    $pdf = $converter->create($argv[3], $xml);
    if (file_put_contents($argv[4], $pdf) !== strlen($pdf)) {
        throw new \RuntimeException('Test-Ausgabe konnte nicht gespeichert werden.');
    }
    echo "PASS: PDF/A-3 + Factur-X mit Mustang validiert\n";
}
