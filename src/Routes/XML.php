<?php

namespace Tualo\Office\Zugferd\Routes;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route;

class XML extends \Tualo\Office\Basic\RouteWrapper
{

    public static function scope(): string
    {
        return 'basic';
    }

    public static function register()
    {

        Route::add('/zugferd/xml/(?P<type>\w+)/(?P<id>[\w\-]+)', function ($matches) {
            $db = App::get('session')->getDB();
            try {
                $type = $matches['type'];
                $postdata = json_decode(file_get_contents("php://input"), true);
                $db->direct('set @currentRequest = {postdata}', ['postdata' => json_encode($postdata)]);
                if ($matches['id'] < 0) throw new \Exception('New Report is not allowed');


                $xml = \Tualo\Office\Zugferd\Report::get($type, $matches['id']);

                App::result('xml', $xml);
                App::result('success', true);
            } catch (\Exception $e) {
                App::result('last_sql', $db->last_sql);
                App::result('msg', $e->getMessage());
            }
            App::contenttype('application/json');
        }, array('get'), false);
    }
}
