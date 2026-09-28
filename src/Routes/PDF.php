<?php

namespace Tualo\Office\Zugferd\Routes;

use Easybill\ZUGFeRD2\Validator;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\RemoteBrowser\RemotePDF;
use Tualo\Office\Zugferd\Report;

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
                $pdfRawData = Report::pdf($type, $id, $template, $tablename);
                App::contenttype('application/pdf');
                App::body($pdfRawData);
            } catch (\Exception $e) {

                App::contenttype('application/json');
                App::result('last_sql', $db->last_sql ?? null);
                App::result('msg', $e->getMessage());
                App::result('success', false);
            }
        }, array('get'), false, [], self::scope());
    }
}
