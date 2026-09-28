<?php

namespace Tualo\Office\Zugferd\Routes;

use Easybill\ZUGFeRD2\Validator;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\RemoteBrowser\RemotePDF;

class PDF extends \Tualo\Office\Basic\RouteWrapper
{
    public static function scope(): string
    {
        return 'basic';
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
            '%s --replace-input --add-attachment %s --filename %s --mimetype application/xml %s >/dev/null 2>&1',
            escapeshellarg($qpdf),
            escapeshellarg($xmlFile),
            escapeshellarg('factur-x.xml'),
            escapeshellarg($pdfFile)
        );

        $result = shell_exec($cmd);
        unlink($xmlFile);

        if (!file_exists($pdfFile) || filesize($pdfFile) === 0) {
            return null;
        }

        return $pdfFile;
    }

    public static function register()
    {
        BasicRoute::add('/zugferd/pdf/(?P<type>\w+)/(?P<tablename>[\w\-\_]+)/(?P<template>[\w\-\_]+)/(?P<id>.+)', function ($matches) {
            $db = App::get('session')->getDB();
            try {
                $type = $matches['type'];
                $id = $matches['id'];
                $tablename = $matches['tablename'];
                $template = $matches['template'];

                $pdfRawData = RemotePDF::get($tablename, $template, $id);
                $pdfData = null;
                $embeddedXml = false;
                if (isset($pdfRawData['filename']) && file_exists($pdfRawData['filename'])) {
                    $pdfData = file_get_contents($pdfRawData['filename']);
                    $pdfFile = $pdfRawData['filename'];
                    unlink($pdfRawData['filename']);
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
                    }
                }

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
                ]);
            } catch (\Exception $e) {
                App::result('last_sql', $db->last_sql ?? null);
                App::result('msg', $e->getMessage());
                App::result('success', false);
            }
            App::contenttype('application/json');
        }, array('get'), false, [], self::scope());
    }
}
