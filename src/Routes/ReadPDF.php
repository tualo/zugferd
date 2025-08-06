<?php

namespace Tualo\Office\Zugferd\Routes;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Route;
use Tualo\Office\Basic\IRoute;
use Tualo\Office\DS\DSTable;
use Tualo\Office\DS\DSFilter;
use Tualo\Office\Report\Report as R;


use horstoeko\zugferd\ZugferdDocumentPdfReader;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use Tualo\Office\FAX\Routes\PUG;
use Tualo\Office\PUG\PUG2;

class ReadPDF implements IRoute
{
    public static function register()
    {

        Route::add('/zugferd/read', function ($matches) {

            try {
                $source = App::get('basePath') . '/ZUGFeRD_8290524992.pdf';
                $source = App::get('basePath') . '/RE-4739350-20240930-5625796.pdf';

                $document = ZugferdDocumentPdfReader::getXmlFromContent(file_get_contents($source));
                var_dump($document);

                $pdfContent = file_get_contents($source);
                $document = ZugferdDocumentPdfReader::readAndGuessFromContent($pdfContent);
                print_r($document);
            } catch (\Exception $e) {
                echo 1;
                echo $e->getMessage();
            }
        }, array('get'), false);
    }
}
