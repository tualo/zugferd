<?php

namespace Tualo\Office\Zugferd;

/**
 * Renders a CII (ZUGFeRD/Factur-X) XML invoice as a plain text layout,
 * at most 80 characters wide.
 */
class PlainText
{
    private const WIDTH = 80;
    private const TYPE_CODES = [
        '380' => 'RECHNUNG',
        '381' => 'GUTSCHRIFT',
        '384' => 'RECHNUNGSKORREKTUR',
        '389' => 'EIGENBELEG',
    ];

    public static function render(string $xml): string
    {
        $document = new \DOMDocument();
        if (!@$document->loadXML($xml)) {
            throw new \Exception('XML konnte nicht gelesen werden');
        }
        $xpath = new \DOMXPath($document);
        $q = fn(string $query, ?\DOMNode $context = null): string =>
            trim($xpath->evaluate('string(' . $query . ')', $context));

        $out = [];
        $out[] = str_repeat('=', self::WIDTH);

        $typeCode = $q("//*[local-name()='ExchangedDocument']/*[local-name()='TypeCode']");
        $title = (self::TYPE_CODES[$typeCode] ?? 'BELEG') . ' Nr. '
            . $q("//*[local-name()='ExchangedDocument']/*[local-name()='ID']");
        $date = self::date102($q("//*[local-name()='ExchangedDocument']/*[local-name()='IssueDateTime']//*[local-name()='DateTimeString']"));
        $out[] = self::spread($title, $date === '' ? '' : 'Datum: ' . $date);
        $out[] = str_repeat('-', self::WIDTH);

        $agreement = $xpath->query("//*[local-name()='ApplicableHeaderTradeAgreement']")->item(0);
        $seller = self::party($xpath, $agreement, 'SellerTradeParty');
        $buyer = self::party($xpath, $agreement, 'BuyerTradeParty');
        foreach (self::columns(array_merge(['Verkäufer'], $seller), array_merge(['Käufer'], $buyer)) as $line) {
            $out[] = $line;
        }
        $reference = $q("*[local-name()='BuyerReference']", $agreement);
        if ($reference !== '') $out[] = 'Referenz: ' . $reference;

        $out[] = str_repeat('-', self::WIDTH);
        $out[] = 'Pos ' . self::padRight('Bezeichnung', 36)
            . self::padLeft('Menge', 10) . ' ' . self::padRight('ME', 3)
            . self::padLeft('Preis', 13) . self::padLeft('Netto', 13);
        $out[] = str_repeat('-', self::WIDTH);

        foreach ($xpath->query("//*[local-name()='IncludedSupplyChainTradeLineItem']") as $line) {
            $nameLines = self::wrap($q(".//*[local-name()='SpecifiedTradeProduct']/*[local-name()='Name']", $line), 36);
            $out[] = self::padLeft($q(".//*[local-name()='LineID']", $line), 3)
                . ' ' . self::padRight($nameLines[0] ?? '', 36)
                . self::padLeft(self::number($q(".//*[local-name()='BilledQuantity']", $line), 0, 4), 10)
                . ' ' . self::padRight($q(".//*[local-name()='BilledQuantity']/@unitCode", $line), 3)
                . self::padLeft(self::number($q(".//*[local-name()='NetPriceProductTradePrice']/*[local-name()='ChargeAmount']", $line), 2, 6), 13)
                . self::padLeft(self::number($q(".//*[local-name()='SpecifiedTradeSettlementLineMonetarySummation']/*[local-name()='LineTotalAmount']", $line), 2, 2), 13);
            foreach (array_slice($nameLines, 1) as $continuation) {
                $out[] = '    ' . $continuation;
            }
            $rate = $q(".//*[local-name()='ApplicableTradeTax']/*[local-name()='RateApplicablePercent']", $line);
            if ($rate !== '') $out[] = '    USt ' . self::number($rate, 2, 2) . ' %';
        }

        $settlement = $xpath->query("//*[local-name()='ApplicableHeaderTradeSettlement']")->item(0);
        foreach ($xpath->query("*[local-name()='SpecifiedTradeAllowanceCharge']", $settlement) as $adjustment) {
            $isCharge = $q(".//*[local-name()='ChargeIndicator']/*", $adjustment) === 'true';
            $reason = $q("*[local-name()='Reason']", $adjustment) ?: ($isCharge ? 'Zuschlag' : 'Abschlag');
            $amount = self::number($q("*[local-name()='ActualAmount']", $adjustment), 2, 2);
            $out[] = self::spread(
                '    ' . ($isCharge ? 'Zuschlag: ' : 'Abschlag: ') . $reason,
                ($isCharge ? '' : '-') . $amount
            );
        }

        $out[] = str_repeat('-', self::WIDTH);
        $currency = $q("*[local-name()='InvoiceCurrencyCode']", $settlement) ?: 'EUR';
        $summation = $xpath->query("*[local-name()='SpecifiedTradeSettlementHeaderMonetarySummation']", $settlement)->item(0);
        $total = fn(string $tag): string => $q("*[local-name()='" . $tag . "']", $summation);

        self::totalLine($out, 'Summe Positionen', $total('LineTotalAmount'));
        self::totalLine($out, 'Zuschläge', $total('ChargeTotalAmount'), true);
        self::totalLine($out, 'Abschläge', $total('AllowanceTotalAmount'), true, true);
        self::totalLine($out, 'Nettobetrag', $total('TaxBasisTotalAmount'));
        foreach ($xpath->query("*[local-name()='ApplicableTradeTax']", $settlement) as $tax) {
            self::totalLine(
                $out,
                'USt ' . self::number($q("*[local-name()='RateApplicablePercent']", $tax), 2, 2)
                    . ' % auf ' . self::number($q("*[local-name()='BasisAmount']", $tax), 2, 2),
                $q("*[local-name()='CalculatedAmount']", $tax)
            );
        }
        self::totalLine($out, 'Gesamtbetrag ' . $currency, $total('GrandTotalAmount'));
        self::totalLine($out, 'Gezahlt', $total('TotalPrepaidAmount'), true);
        self::totalLine($out, 'Zahlbetrag ' . $currency, $total('DuePayableAmount'));
        $out[] = str_repeat('=', self::WIDTH);

        foreach ($xpath->query("*[local-name()='SpecifiedTradePaymentTerms']", $settlement) as $terms) {
            foreach (self::wrap($q("*[local-name()='Description']", $terms), self::WIDTH) as $line) {
                $out[] = $line;
            }
            $due = self::date102($q(".//*[local-name()='DueDateDateTime']//*[local-name()='DateTimeString']", $terms));
            if ($due !== '') $out[] = 'Fällig am: ' . $due;
        }

        return implode("\n", $out) . "\n";
    }

    /** @return string[] name and address lines of a trade party */
    private static function party(\DOMXPath $xpath, ?\DOMNode $agreement, string $tag): array
    {
        $party = $xpath->query("*[local-name()='" . $tag . "']", $agreement)->item(0);
        if ($party === null) return [];
        $q = fn(string $query): string => trim($xpath->evaluate('string(' . $query . ')', $party));
        $lines = [];
        foreach (
            [
                $q("*[local-name()='Name']"),
                $q(".//*[local-name()='PostalTradeAddress']/*[local-name()='LineOne']"),
                $q(".//*[local-name()='PostalTradeAddress']/*[local-name()='LineTwo']"),
                trim(
                    $q(".//*[local-name()='PostalTradeAddress']/*[local-name()='PostcodeCode']") . ' '
                        . $q(".//*[local-name()='PostalTradeAddress']/*[local-name()='CityName']") . ', '
                        . $q(".//*[local-name()='PostalTradeAddress']/*[local-name()='CountryID']"),
                    ' ,'
                ),
            ] as $line
        ) {
            if ($line !== '') $lines[] = $line;
        }
        foreach ($xpath->query(".//*[local-name()='SpecifiedTaxRegistration']/*[local-name()='ID']", $party) as $registration) {
            $scheme = $registration->attributes->getNamedItem('schemeID')?->nodeValue;
            $label = ['VA' => 'USt-IdNr.', 'FC' => 'Steuernr.'][$scheme] ?? 'Steuerkennung';
            $lines[] = $label . ': ' . trim($registration->textContent);
        }
        return $lines;
    }

    /** Two 39-character columns separated by two spaces. @return string[] */
    private static function columns(array $left, array $right): array
    {
        $lines = [];
        for ($index = 0; $index < max(count($left), count($right)); $index++) {
            $lines[] = rtrim(
                self::padRight(self::cut($left[$index] ?? '', 39), 39) . '  '
                    . self::cut($right[$index] ?? '', 39)
            );
        }
        return $lines;
    }

    private static function totalLine(array &$out, string $label, string $value, bool $skipZero = false, bool $negative = false): void
    {
        if ($value === '') return;
        if ($skipZero && (float) $value == 0.0) return;
        $amount = ($negative && (float) $value != 0.0 ? '-' : '') . self::number($value, 2, 2);
        $out[] = self::padLeft($label, self::WIDTH - 14) . self::padLeft($amount, 14);
    }

    private static function number(string $value, int $minDecimals, int $maxDecimals): string
    {
        if ($value === '') return '';
        $float = (float) $value;
        $decimals = $minDecimals;
        while ($decimals < $maxDecimals && round($float, $decimals) != $float) $decimals++;
        return number_format($float, $decimals, ',', '.');
    }

    private static function date102(string $value): string
    {
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $match)) return $value;
        return $match[3] . '.' . $match[2] . '.' . $match[1];
    }

    /** Left text and right text on one line, filled with spaces. */
    private static function spread(string $left, string $right): string
    {
        $gap = self::WIDTH - mb_strlen($left) - mb_strlen($right);
        if ($gap < 1) $left = self::cut($left, self::WIDTH - mb_strlen($right) - 1);
        return rtrim($left . str_repeat(' ', max(1, self::WIDTH - mb_strlen($left) - mb_strlen($right))) . $right);
    }

    /** Multibyte-safe word wrap. @return string[] */
    private static function wrap(string $text, int $width): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($text)) as $paragraph) {
            $current = '';
            foreach (preg_split('/\s+/', trim($paragraph)) as $word) {
                if ($word === '') continue;
                while (mb_strlen($word) > $width) {
                    if ($current !== '') {
                        $lines[] = $current;
                        $current = '';
                    }
                    $lines[] = mb_substr($word, 0, $width);
                    $word = mb_substr($word, $width);
                }
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if (mb_strlen($candidate) > $width) {
                    $lines[] = $current;
                    $current = $word;
                } else {
                    $current = $candidate;
                }
            }
            if ($current !== '') $lines[] = $current;
        }
        return $lines === [] ? [''] : $lines;
    }

    private static function cut(string $text, int $width): string
    {
        if (mb_strlen($text) <= $width) return $text;
        return mb_substr($text, 0, $width - 1) . '…';
    }

    private static function padRight(string $text, int $width): string
    {
        return $text . str_repeat(' ', max(0, $width - mb_strlen($text)));
    }

    private static function padLeft(string $text, int $width): string
    {
        return str_repeat(' ', max(0, $width - mb_strlen($text))) . $text;
    }
}
