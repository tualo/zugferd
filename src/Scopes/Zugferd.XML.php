<?php

namespace Tualo\Office\Zugferd\Scopes;

use Tualo\Office\Basic\Scope;

class ZugferdXML extends Scope
{
    protected static string $identifier = 'zugferd.xml';
    protected static array $name = [
        'EN' => 'Zugferd XML',
        'DE' => 'Zugferd XML',
    ];
    protected static array $description = [
        'EN' => 'Access and manage Zugferd XML files.',
        'DE' => 'Zugferd XML-Dateien zugreifen und verwalten.',
    ];
}
