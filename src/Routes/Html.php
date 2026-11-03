<?php

namespace Tualo\Office\Zugferd\Routes;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route;

class Html extends \Tualo\Office\Basic\RouteWrapper
{

    public static function scope(): string
    {
        return 'basic';
    }

    public static function register()
    {



        // Mustang-HTML-Visualisierung der Rechnung (XRechnung-Viewer, self-contained)
        Route::add('/zugferd/htmlview/(?P<type>\w+)/(?P<id>[\w\-]+)', function ($matches) {
            $db = App::get('session')->getDB();
            try {
                if ($matches['id'] < 0) throw new \Exception('New Report is not allowed');
                $language = strtolower(trim((string) ($_GET['lang'] ?? 'de')));
                $html = \Tualo\Office\Zugferd\Report::html($matches['type'], $matches['id'], $language);
                App::contenttype('text/html; charset=utf-8');
                App::body($html);
            } catch (\Exception $e) {
                App::contenttype('application/json');
                App::result('last_sql', $db->last_sql ?? null);
                App::result('msg', $e->getMessage());
                App::result('success', false);
            }
        }, array('get'), true);
    }
}
