<?php

namespace Tualo\Office\Zugferd\Routes;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\Route;
use Tualo\Office\Basic\IRoute;
use Tualo\Office\DS\DSTable;
use Tualo\Office\DS\DSFilter;
use Tualo\Office\Report\Report as R;


use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;


class PDF implements IRoute
{
    public static function register()
    {

        Route::add('/zugferd/pdf/(?P<template>\w+)/(?P<type>\w+)/(?P<id>[\w\-]+)', function ($matches) {
            $db = App::get('session')->getDB();
            try {
                $type = $matches['type'];
                $postdata = json_decode(file_get_contents("php://input"), true);
                $db->direct('set @currentRequest = {postdata}', ['postdata' => json_encode($postdata)]);
                if ($matches['id'] < 0) throw new \Exception('New Report is not allowed');
                $data = R::get($type, $matches['id']);
                if (is_null($data)) throw new \Exception('Report not found');

                //print_r($data); exit();

                // Create an empty invoice document in the EN16931 profile
                $document = ZugferdDocumentBuilder::CreateNew(ZugferdProfiles::PROFILE_EN16931)
                    ->setDocumentSeller(
                        (isset($data['zf_sender_name'])?$data['zf_sender_name']:'No Information given'),
                        (isset($data['zf_sender_id'])?$data['zf_sender_id']:'No Information given'), 
                    )
                    ->setDocumentBusinessProcess('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');

                $document->setDocumentInformation(
                    $data['id'], 
                    ZugferdInvoiceType::INVOICE, 
                    \DateTime::createFromFormat("Y-m-d", $data['date']), 
                    "EUR"
                )
                    ->setDocumentSupplyChainEvent(\DateTime::createFromFormat("Y-m-d", $data['service_period_start']));
                    

                foreach( $data['texts'] as $k=>$txts) {
                    if ($k=='head'){
                        foreach($txts as $item){
                            $document->addDocumentNote($item['txt']);
                        }
                    }
                }

                foreach( $data['texts'] as $k=>$txts) {
                    if ($k=='foot'){
                        foreach($txts as $item){
                            $document->addDocumentPaymentTerm($item['txt']);
                        }
                    }
                }
                
                if (is_string($data['tax_registration']))
                    $data['tax_registration'] = json_decode($data['tax_registration'],true);
                if (is_string($data['seller_global_ids']))
                    $data['seller_global_ids'] = json_decode($data['seller_global_ids'],true);
                if (is_string($data['seller_information']))
                    $data['seller_information'] = json_decode($data['seller_information'],true);
                if (is_string($data['taxes']))
                    $data['taxes'] = json_decode($data['taxes'],true);
            
                foreach( $data['tax_registration'] as $index=>$item) {
                    $document->addDocumentSellerTaxRegistration($item['type'], $item['value']);
                }

                foreach( $data['seller_global_ids'] as $type=>$value) {
                    $document->addDocumentSellerGlobalId($value, $type);
                }

                if (isset($data['seller_information'])){
                    if (isset($data['seller_information']['name']) && isset($data['seller_id']))
                        $document->setDocumentSeller($data['seller_information']['name'], $data['seller_id']);
                    $document->setDocumentSellerAddress(

                        isset($data['seller_information']['line1'])? $data['seller_information']['line1']:'',
                        isset($data['seller_information']['line2'])? $data['seller_information']['line2']:'',
                        isset($data['seller_information']['line3'])? $data['seller_information']['line3']:'',
                        isset($data['seller_information']['postcode'])? $data['seller_information']['postcode']:'',
                        isset($data['seller_information']['city'])? $data['seller_information']['city']:'',
                        isset($data['seller_information']['country'])? $data['seller_information']['country']:''
                        
                    );
                }

                if (isset($data['buyer_information'])){
                    if (isset($data['buyer_information']['name']) && isset($data['seller_id']))
                        $document->setDocumentBuyer($data['buyer_information']['name'], $data['seller_id']);
                    $document->setDocumentBuyerAddress(

                        isset($data['buyer_information']['line1'])? $data['buyer_information']['line1']:'',
                        isset($data['buyer_information']['line2'])? $data['buyer_information']['line2']:'',
                        isset($data['buyer_information']['line3'])? $data['buyer_information']['line3']:'',
                        isset($data['buyer_information']['postcode'])? $data['buyer_information']['postcode']:'',
                        isset($data['buyer_information']['city'])? $data['buyer_information']['city']:'',
                        isset($data['buyer_information']['country'])? $data['buyer_information']['country']:''
                        
                    );
                }
                $document->setDocumentBuyerReference($data['reference']);

                foreach( $data['taxes'] as $item) {
                    $document->addDocumentTax(
                        $item['category'],
                        $item['type'],
                        $item['gross'],
                        $item['tax'],
                        $item['rate']
                    );
                }
                $document->setDocumentSummation(
                    $data['gross'],
                    $data['open'],
                    $data['net']
                );


                foreach( $data['positions'] as $position) {

                    $document->addNewPosition("1")
                    ->setDocumentPositionProductDetails(
                        $position['article'],
                        // $position['note'],
                        // "Trennblätter A4", "", "TB100A4", null, "0160", "4012345001235"
                    )
                    ->setDocumentPositionNetPrice($position['net'])
                    ->setDocumentPositionQuantity($position['amount'], "H87")
                    ->addDocumentPositionTax('S', 'VAT', $position['tax'])
                    ->setDocumentPositionLineSummation($position['gross']);

                }


                /*
                // Save merged PDF (existing original and XML) to a file
                $pdfBuilder = new ZugferdDocumentPdfBuilder($document, "/tmp/existingprintlayout.pdf");
                $pdfBuilder->generateDocument()->saveDocument("/tmp/merged.pdf");
                */
                $document->writeFile(App::get('tempPath'). "/factur-x.xml");
                echo (file_get_contents(App::get('tempPath'). "/factur-x.xml")); 
                exit();
                // Alternatively, you can also return the merged output (existing original and XML) as a binary string
                $pdfBuilder = new ZugferdDocumentPdfBuilder($document, "/tmp/existingprintlayout.pdf");
                $pdfBinaryString = $pdfBuilder->generateDocument()->downloadString("merged.pdf");

            } catch (\Exception $e) {
                App::result('last_sql', $db->last_sql);
                App::result('msg', $e->getMessage());
            }
            App::contenttype('application/json');
        }, array('get'), false);
    }
}
