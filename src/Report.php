<?php

namespace Tualo\Office\Zugferd;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Route;
use Tualo\Office\Basic\IRoute;
use Tualo\Office\DS\DSTable;
use Tualo\Office\DS\DSFilter;
use Tualo\Office\Report\Report as R;

use Tualo\Office\RemoteBrowser\RemotePDF;


use Easybill\ZUGFeRD2\Builder;
use Easybill\ZUGFeRD2\Model\Amount;
use Easybill\ZUGFeRD2\Model\BinaryObject;
use Easybill\ZUGFeRD2\Model\CreditorFinancialAccount;
use Easybill\ZUGFeRD2\Model\CreditorFinancialInstitution;
use Easybill\ZUGFeRD2\Model\CrossIndustryInvoice;
use Easybill\ZUGFeRD2\Model\DateTime;
use Easybill\ZUGFeRD2\Model\DocumentContextParameter;
use Easybill\ZUGFeRD2\Model\DocumentLineDocument;
use Easybill\ZUGFeRD2\Model\ExchangedDocument;
use Easybill\ZUGFeRD2\Model\ExchangedDocumentContext;
use Easybill\ZUGFeRD2\Model\HeaderTradeAgreement;
use Easybill\ZUGFeRD2\Model\HeaderTradeDelivery;
use Easybill\ZUGFeRD2\Model\HeaderTradeSettlement;
use Easybill\ZUGFeRD2\Model\Id;
use Easybill\ZUGFeRD2\Model\LineTradeAgreement;
use Easybill\ZUGFeRD2\Model\LineTradeDelivery;
use Easybill\ZUGFeRD2\Model\LineTradeSettlement;
use Easybill\ZUGFeRD2\Model\Note;
use Easybill\ZUGFeRD2\Model\Period;
use Easybill\ZUGFeRD2\Model\Quantity;
use Easybill\ZUGFeRD2\Model\ReferencedDocument;
use Easybill\ZUGFeRD2\Model\SupplyChainEvent;
use Easybill\ZUGFeRD2\Model\SupplyChainTradeLineItem;
use Easybill\ZUGFeRD2\Model\SupplyChainTradeTransaction;
use Easybill\ZUGFeRD2\Model\TaxRegistration;
use Easybill\ZUGFeRD2\Model\TradeAddress;
use Easybill\ZUGFeRD2\Model\TradeContact;
use Easybill\ZUGFeRD2\Model\TradeParty;
use Easybill\ZUGFeRD2\Model\TradePaymentTerms;
use Easybill\ZUGFeRD2\Model\TradePrice;
use Easybill\ZUGFeRD2\Model\TradeProduct;
use Easybill\ZUGFeRD2\Model\TradeSettlementHeaderMonetarySummation;
use Easybill\ZUGFeRD2\Model\TradeSettlementLineMonetarySummation;
use Easybill\ZUGFeRD2\Model\TradeSettlementPaymentMeans;
use Easybill\ZUGFeRD2\Model\TradeTax;
use Easybill\ZUGFeRD2\Model\UniversalCommunication;
use Easybill\ZUGFeRD2\Tests\Traits\AssertXmlOutputTrait;
use Easybill\ZUGFeRD2\Validator;
use PHPUnit\Framework\TestCase;


class Report
{
    private static function normalizeAddressValue(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        if ($value === '') return null;
        return $value;
    }

    private static function unwrapData(array $data): array
    {
        while (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        return $data;
    }

    private static function parseAddressInfo(array $data, string $prefix = 'buyer'): array
    {
        $base = [];
        $addressText = trim((string) ($data['address'] ?? ''));
        if ($addressText !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $addressText);
            $clean = [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line !== '') $clean[] = $line;
            }

            if (count($clean) >= 1) {
                $base['line1'] = $clean[0];
            }
            if (count($clean) >= 2) {
                $base['line2'] = $clean[1];
            }
            if (count($clean) >= 3) {
                $base['line3'] = $clean[2];
            }
            if (count($clean) >= 4) {
                $match = preg_match('/^(\d{4,5})\s+(.+)$/u', $clean[count($clean) - 1], $m);
                if ($match === 1) {
                    $base['postcode'] = $m[1];
                    $base['city'] = $m[2];
                } else {
                    $base['city'] = $clean[count($clean) - 1];
                }
            }
        }

        $explicit = $data[$prefix . '_information'] ?? [];
        if (!is_array($explicit)) {
            $explicit = [];
        }
        if (!empty($explicit['line1']) && empty($base['line1'])) {
            $base['line1'] = $explicit['line1'];
        }
        if (!empty($explicit['line2']) && empty($base['line2'])) {
            $base['line2'] = $explicit['line2'];
        }
        if (!empty($explicit['line3']) && empty($base['line3'])) {
            $base['line3'] = $explicit['line3'];
        }
        if (empty($base['postcode']) && !empty($explicit['postcode'])) {
            $base['postcode'] = $explicit['postcode'];
        }
        if (empty($base['city']) && !empty($explicit['city'])) {
            $base['city'] = $explicit['city'];
        }
        if (empty($base['country']) && !empty($explicit['country'])) {
            $base['country'] = $explicit['country'];
        }

        return array_merge([
            'line1' => '',
            'line2' => '',
            'line3' => '',
            'postcode' => '',
            'city' => '',
            'country' => 'DE',
        ], $base, $explicit);
    }

    private static function ensureXmlRequiredElements(string $xml, array $data): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (!@$dom->loadXML($xml)) {
            return $xml;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $sellerInfo = self::parseAddressInfo($data, 'seller');
        $buyerInfo = self::parseAddressInfo($data, 'buyer');

        foreach (
            [
                ['seller', $sellerInfo],
                ['buyer', $buyerInfo],
            ] as [$role, $info]
        ) {
            $partyName = ucfirst($role) . 'TradeParty';
            $query = sprintf("//*[local-name()='%s']", $partyName);
            $party = $xpath->query($query)->item(0);
            if ($party === null) {
                continue;
            }

            $address = $xpath->query(".//*[local-name()='PostalTradeAddress']", $party)->item(0);
            if ($address === null) {
                $address = $dom->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:PostalTradeAddress');
                $party->appendChild($address);
            }

            $countryId = strtoupper(self::normalizeAddressValue($info['country'] ?? 'DE') ?? 'DE');
            $cityName = self::normalizeAddressValue($info['city'] ?? null);
            $lineOne = self::normalizeAddressValue($info['line3'] ?? $info['line1'] ?? null);
            $lineTwo = self::normalizeAddressValue($info['line2'] ?? null);

            if ($cityName !== null && $xpath->query(".//*[local-name()='CityName']", $address)->length === 0) {
                $address->appendChild($dom->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:CityName', $cityName));
            }
            if ($countryId !== '' && $xpath->query(".//*[local-name()='CountryID']", $address)->length === 0) {
                $address->appendChild($dom->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:CountryID', $countryId));
            }
            if ($lineOne !== null && $xpath->query(".//*[local-name()='LineOne']", $address)->length === 0) {
                $address->appendChild($dom->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:LineOne', $lineOne));
            }
            if ($lineTwo !== null && $xpath->query(".//*[local-name()='LineTwo']", $address)->length === 0) {
                $address->appendChild($dom->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:LineTwo', $lineTwo));
            }
        }

        $settlementNode = $xpath->query("//*[local-name()='ApplicableHeaderTradeSettlement']")->item(0);
        if ($settlementNode !== null) {
            $hasInvoiceCurrency = $xpath->query(".//*[local-name()='InvoiceCurrencyCode']", $settlementNode)->length > 0;
            if (!$hasInvoiceCurrency) {
                $firstTax = $xpath->query(".//*[local-name()='ApplicableTradeTax']", $settlementNode)->item(0);
                $invoiceCurrency = $dom->createElementNS('urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100', 'ram:InvoiceCurrencyCode', 'EUR');
                if ($firstTax !== null) {
                    $settlementNode->insertBefore($invoiceCurrency, $firstTax);
                } else {
                    $settlementNode->appendChild($invoiceCurrency);
                }
            }
        }

        return $dom->saveXML();
    }

    public static function get(string $type, int $id): string
    {

        $data = self::unwrapData(R::get($type, $id));
        if (is_null($data)) throw new \Exception('Report not found');
        $invoice = new CrossIndustryInvoice();
        $invoice->exchangedDocumentContext = new ExchangedDocumentContext();
        $invoice->exchangedDocumentContext->documentContextParameter = new DocumentContextParameter();
        $invoice->exchangedDocumentContext->documentContextParameter->id = Builder::GUIDELINE_SPECIFIED_DOCUMENT_CONTEXT_ID_XRECHNUNG;

        $invoice->exchangedDocument = new ExchangedDocument();
        $invoice->exchangedDocument->id = (string) ($data['document_no'] ?? $data['id']);
        $invoice->exchangedDocument->typeCode = '380';
        $invoice->exchangedDocument->issueDateTime = DateTime::create(102, str_replace('-', '', $data['date']));

        $invoice->supplyChainTradeTransaction = new SupplyChainTradeTransaction();
        foreach (($data['positions'] ?? []) as $position) {
            $item = new SupplyChainTradeLineItem();
            $item->associatedDocumentLineDocument = DocumentLineDocument::create((string) $position['position']);

            $item->specifiedTradeProduct = new TradeProduct();
            $item->specifiedTradeProduct->name = trim($position['artikel_text']);
            $item->specifiedTradeProduct->sellerAssignedID = (string) $position['article'];

            $item->tradeAgreement = new LineTradeAgreement();
            $item->tradeAgreement->netPrice = TradePrice::create(number_format((float) $position['singleprice'], 4, '.', ''));

            $item->delivery = new LineTradeDelivery();
            $item->delivery->billedQuantity = Quantity::create(
                number_format((float) $position['amount'], 4, '.', ''),
                $position['einheit_symbol'] ?: 'C62'
            );

            $item->specifiedLineTradeSettlement = new LineTradeSettlement();
            $itemTax = new TradeTax();
            $itemTax->typeCode = 'VAT';
            $itemTax->categoryCode = 'S';
            $itemTax->rateApplicablePercent = number_format((float) $position['tax'], 2, '.', '');
            $item->specifiedLineTradeSettlement->tradeTax[] = $itemTax;
            $item->specifiedLineTradeSettlement->monetarySummation = TradeSettlementLineMonetarySummation::create(
                number_format((float) $position['net'], 2, '.', '')
            );

            $invoice->supplyChainTradeTransaction->lineItems[] = $item;
        }

        $agreement = new HeaderTradeAgreement();
        $agreement->buyerReference = (string) $data['referencenr'];

        $sellerInformation = self::parseAddressInfo($data, 'seller');
        $sellerTradeParty = new TradeParty();
        $sellerTradeParty->name = trim(str_replace(["\r", "\n"], ' ', (string) ($sellerInformation['line1'] ?? '')));
        $sellerTradeParty->postalTradeAddress = new TradeAddress();
        $sellerTradeParty->postalTradeAddress->postcodeCode = self::normalizeAddressValue($sellerInformation['postcode'] ?? null);
        $sellerTradeParty->postalTradeAddress->lineOne = self::normalizeAddressValue($sellerInformation['line3'] ?? $sellerInformation['line1'] ?? null);
        $sellerTradeParty->postalTradeAddress->lineTwo = self::normalizeAddressValue($sellerInformation['line2'] ?? null);
        $sellerTradeParty->postalTradeAddress->cityName = self::normalizeAddressValue($sellerInformation['city'] ?? null);
        $sellerTradeParty->postalTradeAddress->countryID = strtoupper(self::normalizeAddressValue($sellerInformation['country'] ?? 'DE') ?? 'DE');

        if (!empty($sellerInformation['electronic_address'])) {
            $sellerUri = new UniversalCommunication();
            $sellerUri->uriid = Id::create($sellerInformation['electronic_address'], $sellerInformation['electronic_scheme'] ?? null);
            $sellerTradeParty->uriUniversalCommunication = $sellerUri;
        }

        if (!empty($sellerInformation['contact_name']) || !empty($sellerInformation['contact_phone']) || !empty($sellerInformation['contact_email'])) {
            $sellerContact = new TradeContact();
            $sellerContact->personName = $sellerInformation['contact_name'] ?? null;
            if (!empty($sellerInformation['contact_phone'])) {
                $sellerPhone = new UniversalCommunication();
                $sellerPhone->completeNumber = $sellerInformation['contact_phone'];
                $sellerContact->telephoneUniversalCommunication = $sellerPhone;
            }
            if (!empty($sellerInformation['contact_email'])) {
                $sellerEmail = new UniversalCommunication();
                $sellerEmail->uriid = Id::create($sellerInformation['contact_email'], $sellerInformation['electronic_scheme'] ?? null);
                $sellerContact->emailURIUniversalCommunication = $sellerEmail;
            }
            $contactProperty = new \ReflectionProperty($sellerTradeParty, 'definedTradeContact');
            if ((string) $contactProperty->getType() === 'array') {
                $contacts = $contactProperty->getValue($sellerTradeParty);
                $contacts[] = $sellerContact;
                $contactProperty->setValue($sellerTradeParty, $contacts);
            } else {
                $contactProperty->setValue($sellerTradeParty, $sellerContact);
            }
        }

        foreach (($data['tax_registration'] ?? []) as $schemeID => $registrationID) {
            $sellerTradeParty->taxRegistrations[] = TaxRegistration::create($registrationID, $schemeID);
        }
        $agreement->sellerTradeParty = $sellerTradeParty;

        $buyerInformation = self::parseAddressInfo($data, 'buyer');
        $buyerTradeParty = new TradeParty();
        $buyerTradeParty->name = trim(str_replace(["\r", "\n"], ' ', (string) ($buyerInformation['line1'] ?? '')));
        $buyerTradeParty->postalTradeAddress = new TradeAddress();
        $buyerTradeParty->postalTradeAddress->postcodeCode = self::normalizeAddressValue($buyerInformation['postcode'] ?? null);
        $buyerTradeParty->postalTradeAddress->lineOne = self::normalizeAddressValue($buyerInformation['line3'] ?? $buyerInformation['line1'] ?? null);
        $buyerTradeParty->postalTradeAddress->lineTwo = self::normalizeAddressValue($buyerInformation['line2'] ?? null);
        $buyerTradeParty->postalTradeAddress->cityName = self::normalizeAddressValue($buyerInformation['city'] ?? null);
        $buyerTradeParty->postalTradeAddress->countryID = strtoupper(self::normalizeAddressValue($buyerInformation['country'] ?? 'DE') ?? 'DE');

        if (!empty($buyerInformation['electronic_address'])) {
            $buyerUri = new UniversalCommunication();
            $buyerUri->uriid = Id::create($buyerInformation['electronic_address'], $buyerInformation['electronic_address_scheme'] ?? null);
            $buyerTradeParty->uriUniversalCommunication = $buyerUri;
        }

        $agreement->buyerTradeParty = $buyerTradeParty;

        $invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement = $agreement;

        $delivery = new HeaderTradeDelivery();
        $delivery->actualDeliverySupplyChainEvent = new SupplyChainEvent();
        $delivery->actualDeliverySupplyChainEvent->occurrenceDateTime = DateTime::create(
            102,
            str_replace('-', '', $data['service_period_stop'] ?? $data['date'])
        );
        $invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery = $delivery;

        /*
                $paymentMeans1->typeCode = '58';
                $paymentMeans1->information = 'Zahlung per SEPA Überweisung.';
                $paymentMeans1->payeePartyCreditorFinancialAccount = new CreditorFinancialAccount();
                $paymentMeans1->payeePartyCreditorFinancialAccount->ibanId = Id::create('DE02120300000000202051');
                $paymentMeans1->payeePartyCreditorFinancialAccount->AccountName = 'Kunden AG';
                $paymentMeans1->payeeSpecifiedCreditorFinancialInstitution = new CreditorFinancialInstitution();
                $paymentMeans1->payeeSpecifiedCreditorFinancialInstitution->bicId = Id::create('BYLADEM1001');
                */

        /*
                $invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->tradeTaxes[] = $headerTax1 = new TradeTax();
                $headerTax1->typeCode = 'VAT';
                $headerTax1->categoryCode = 'S';
                $headerTax1->basisAmount = Amount::create('275.00');
                $headerTax1->calculatedAmount = Amount::create('19.25');
                $headerTax1->rateApplicablePercent = '7.00';

                $invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->tradeTaxes[] = $headerTax2 = new TradeTax();
                $headerTax2->typeCode = 'VAT';
                $headerTax2->categoryCode = 'S';
                $headerTax2->basisAmount = Amount::create('198.00');
                $headerTax2->calculatedAmount = Amount::create('37.62');
                $headerTax2->rateApplicablePercent = '19.00';

                $invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement->specifiedTradePaymentTerms[] = $paymentTerms = new TradePaymentTerms();
                $paymentTerms->description = 'Zahlbar innerhalb 30 Tagen netto bis 04.04.2018, 3% Skonto innerhalb 10 Tagen bis 15.03.2018';
                */

        $settlement = new HeaderTradeSettlement();
        $settlement->taxCurrencyCode = 'EUR';
        $settlement->invoiceCurrencyCode = 'EUR';
        foreach (($data['taxes'] ?? []) as $tax) {
            $headerTax = new TradeTax();
            $headerTax->typeCode = $tax['type'] ?? 'VAT';
            $headerTax->categoryCode = $tax['category'] ?? 'S';
            $headerTax->basisAmount = Amount::create(number_format((float) ($tax['net'] ?? 0), 2, '.', ''));
            $headerTax->calculatedAmount = Amount::create(number_format((float) ($tax['tax'] ?? 0), 2, '.', ''));
            $headerTax->rateApplicablePercent = number_format((float) ($tax['rate'] ?? 0), 2, '.', '');
            $settlement->tradeTaxes[] = $headerTax;
        }
        $invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement = $settlement;
        $settlement->specifiedTradeSettlementHeaderMonetarySummation = $summation = new TradeSettlementHeaderMonetarySummation();
        $summation->lineTotalAmount = Amount::create(number_format((float) $data['net'], 2, '.', ''));
        $summation->chargeTotalAmount = Amount::create('0.00');
        $summation->allowanceTotalAmount = Amount::create('0.00');
        $summation->taxBasisTotalAmount[] = Amount::create(number_format((float) $data['net'], 2, '.', ''));
        $summation->taxTotalAmount[] = Amount::create(number_format((float) $data['steuer'], 2, '.', ''), 'EUR');
        $summation->grandTotalAmount[] = Amount::create(number_format((float) $data['gross'], 2, '.', ''));
        $summation->totalPrepaidAmount = Amount::create('0.00');
        $summation->duePayableAmount = Amount::create(number_format((float) $data['open'], 2, '.', ''));

        $xml = Builder::create()->transform($invoice);
        return self::ensureXmlRequiredElements($xml, $data);
    }

    public static function validate(string $type, string $id, string $tablename, string $template): array
    {

        $pdfRawData = RemotePDF::get($tablename, $template, $id);
        if (isset($pdfRawData['filename']) && file_exists($pdfRawData['filename'])) {
            $pdfData = file_get_contents($pdfRawData['filename']);
            unlink($pdfRawData['filename']);
        } else {
            $pdfData = null;
        }

        $xml = \Tualo\Office\Zugferd\Report::get($type, $id);

        $validator = new Validator();
        $validationError = $validator->validateAgainstXsd($xml, Validator::SCHEMA_EN16931);
        if ($validationError !== null) {
            throw new \Exception('Ungültige ZUGFeRD-Rechnung: ' . $validationError);
        }

        /*
        App::result('success', true);
        App::result('pdf_rowdata', $pdfRawData);
        App::result('pdf_data', $pdfData !== null ? base64_encode($pdfData) : null);
        App::result('pdf_contenttype', $pdfRawData['contenttype'] ?? 'application/pdf');
        App::result('xml_data', $xml);
        App::result('invoice', [
            'pdf' => $pdfRawData,
            'xml' => $xml,
            'valid' => true,
        ]);
        */
        return [
            'pdf' => $pdfRawData,
            'xml' => $xml,
            'valid' => true,
        ];
    }


    private static function embedXmlIntoPdf(string $pdfFile, string $xmlContent): ?string
    {
        $qpdf = trim((string) shell_exec('command -v qpdf 2>/dev/null || true'));
        if ($qpdf === '') {
            return null;
        }

        $tempDir = App::get('tempPath') ?: sys_get_temp_dir();
        $xmlFile = $tempDir . '/zugferd_' . uniqid('', true) . '.xml';
        $embeddedPdfFile = $tempDir . '/zugferd_' . uniqid('', true) . '.pdf';
        file_put_contents($xmlFile, $xmlContent);

        $cmd = sprintf(
            '%s --replace-input --add-attachment %s --key=factur-x.xml --filename=factur-x.xml --mimetype=application/xml -- %s >/dev/null 2>&1',
            escapeshellarg($qpdf),
            escapeshellarg($xmlFile),
            escapeshellarg($pdfFile)
        );

        $result = shell_exec($cmd);
        unlink($xmlFile);

        if (!file_exists($pdfFile) || filesize($pdfFile) === 0) {
            return null;
        }

        return $pdfFile;
    }

    public static function pdf(string $type, string $id, string $template = '', string $tablename = ''): string
    {

        if ($template === '') $tablename = 'view_blg_list_' . $type;
        if ($template === '') $template = 'report_invoice';


        $pdfRawData = RemotePDF::get($tablename, $template, $id);
        $pdfData = null;
        $embeddedXml = false;
        if (isset($pdfRawData['filename']) && file_exists($pdfRawData['filename'])) {
            $pdfData = file_get_contents($pdfRawData['filename']);
            $pdfFile = $pdfRawData['filename'];
        }

        $xml = \Tualo\Office\Zugferd\Report::get($type, $id);
        $validator = new Validator();
        $validationError = $validator->validateAgainstXsd($xml, Validator::SCHEMA_EN16931);
        if ($validationError !== null) {
            throw new \Exception('Ungültige ZUGFeRD-Rechnung: ' . $validationError);
        }

        if ($pdfData !== null && $pdfFile !== null) {
            $embeddedPdfFile = self::embedXmlIntoPdf($pdfFile, $xml);
            if ($embeddedPdfFile !== null) {
                $pdfData = file_get_contents($embeddedPdfFile);
                $embeddedXml = true;
                unlink($embeddedPdfFile);
            }
        }
        if (isset($pdfFile) && file_exists($pdfFile)) {
            unlink($pdfFile);
        }

        /*
        App::result('success', true);
        App::result('embedded_xml', $embeddedXml);
        App::result('pdf_rowdata', $pdfRawData);
        App::result('pdf_data', $pdfData !== null ? base64_encode($pdfData) : null);
        App::result('pdf_contenttype', $pdfRawData['contenttype'] ?? 'application/pdf');
        App::result('xml_data', $xml);
        App::result('invoice', [
            'pdf' => $pdfRawData,
            'xml' => $xml,
            'embedded_xml' => $embeddedXml,
            'valid' => true,
        ]);*/
        return $pdfRawData;
    }
}
