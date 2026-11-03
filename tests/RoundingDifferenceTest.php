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

    if ($argc !== 2) {
        fwrite(STDERR, "Usage: php tests/RoundingDifferenceTest.php mustang.jar\n");
        exit(1);
    }

    function check(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException($message);
    }

    function invoice(float $net, float $tax, array $positions): array
    {
        $lines = [];
        foreach ($positions as $index => [$lineNet, $rate]) {
            $lines[] = [
                'position' => $index + 1, 'article_text' => 'Artikel ' . ($index + 1), 'article_id' => 'A' . ($index + 1),
                'singleprice' => $lineNet, 'amount' => 1, 'unit_code' => 'C62',
                'tax_rate' => $rate, 'net' => $lineNet,
            ];
        }
        return [
            'id' => 1, 'date' => '2026-10-31', 'reference' => 'TEST',
            'net' => $net, 'steuer' => $tax, 'gross' => $net + $tax, 'open' => $net + $tax,
            'seller_information' => [
                'line1' => 'Test Seller', 'line3' => 'Teststrasse 1',
                'city' => 'Berlin', 'postcode' => '10115', 'country' => 'DE',
            ],
            'buyer_information' => [
                'line1' => 'Test Buyer', 'line3' => 'Teststrasse 2',
                'city' => 'Berlin', 'postcode' => '10115', 'country' => 'DE',
            ],
            'tax_registration' => ['VA' => 'DE123456789'],
            'en16931_positions' => $lines,
            'taxes' => [['net' => $net, 'tax' => $tax, 'rate' => 19]],
        ];
    }

    $profile = Builder::GUIDELINE_SPECIFIED_DOCUMENT_CONTEXT_ID_EN16931;
    $converter = new FacturX($argv[1], $argv[1]);
    $cases = [
        'ohne Differenz' => [invoice(100.00, 19.00, [[50.00, 19], [50.00, 19]]), null],
        'Zuschlag' => [invoice(100.01, 19.00, [[50.00, 19], [50.00, 19]]), 'true'],
        'Abschlag' => [invoice(99.99, 19.00, [[50.00, 19], [50.00, 19]]), 'false'],
    ];
    foreach ($cases as $name => [$data, $chargeIndicator]) {
        \Tualo\Office\Report\Report::$data = $data;
        $xml = Report::get('test', 1, $profile);
        $converter->validateXml($xml);
        $document = new \DOMDocument();
        $document->loadXML($xml);
        $xpath = new \DOMXPath($document);
        $indicators = $xpath->query("//*[local-name()='SpecifiedTradeAllowanceCharge']/*[local-name()='ChargeIndicator']/*");
        if ($chargeIndicator === null) {
            check($indicators->length === 0, $name . ': unerwarteter Zu-/Abschlag');
        } else {
            check($indicators->length === 1 && $indicators->item(0)->textContent === $chargeIndicator, $name . ': Rundungsdifferenz fehlt');
            $amount = $xpath->evaluate("string(//*[local-name()='SpecifiedTradeAllowanceCharge']/*[local-name()='ActualAmount'])");
            check($amount === '0.01', $name . ': falscher Ausgleichsbetrag ' . $amount);
        }
        $basis = $xpath->evaluate("string(//*[local-name()='SpecifiedTradeSettlementHeaderMonetarySummation']/*[local-name()='TaxBasisTotalAmount'])");
        check($basis === number_format($data['net'], 2, '.', ''), $name . ': Steuerbasis entspricht nicht dem Beleg');
        echo "PASS: $name\n";
    }

    foreach ([
        'große Abweichung' => invoice(101.00, 19.19, [[50.00, 19], [50.00, 19]]),
        'fehlender Steuersatz' => invoice(100.00, 19.00, [[50.00, 19], [50.00, 7]]),
    ] as $name => $data) {
        \Tualo\Office\Report\Report::$data = $data;
        try {
            Report::get('test', 1, $profile);
            throw new \LogicException($name . ' wurde nicht abgelehnt');
        } catch (\LogicException $error) {
            throw $error;
        } catch (\Exception $error) {
            check(str_contains($error->getMessage(), 'Steuer'), $name . ': unerwarteter Fehler ' . $error->getMessage());
        }
        echo "PASS: $name wird abgelehnt\n";
    }
}
