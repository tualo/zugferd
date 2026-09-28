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

    public static function register()
    {
        BasicRoute::add('/zugferd/pdf/(?P<type>\w+)/(?P<tablename>[\w\-\_]+)/(?P<template>[\w\-\_]+)/(?P<id>.+)', function ($matches) {
            $db = App::get('session')->getDB();
            try {
                $type = $matches['type'];
                $id = $matches['id'];
                $tablename = $matches['tablename'];
                $template = $matches['template'];

                $pdfRowData = RemotePDF::get($tablename, $template, $id);
                if (isset($pdfRowData['filename']) && file_exists($pdfRowData['filename'])) {
                    $pdfData = file_get_contents($pdfRowData['filename']);
                    unlink($pdfRowData['filename']);
                } else {
                    $pdfData = null;
                }

                $xml = \Tualo\Office\Zugferd\Report::get($type, $id);

                $validator = new Validator();
                $validationError = $validator->validateAgainstXsd($xml, Validator::SCHEMA_EN16931);
                if ($validationError !== null) {
                    throw new \Exception('Ungültige ZUGFeRD-Rechnung: ' . $validationError);
                }

                App::result('success', true);
                App::result('pdf_rowdata', $pdfRowData);
                App::result('pdf_data', $pdfData !== null ? base64_encode($pdfData) : null);
                App::result('pdf_contenttype', $pdfRowData['contenttype'] ?? 'application/pdf');
                App::result('xml_data', $xml);
                App::result('invoice', [
                    'pdf' => $pdfRowData,
                    'xml' => $xml,
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
