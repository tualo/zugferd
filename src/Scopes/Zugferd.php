<?php

namespace Tualo\Office\Zugferd\Scopes;

use Tualo\Office\Basic\Scope;

class ZugferdScope extends Scope
{
    protected static string $identifier = 'zugferd';
    protected static array $name = [
        'EN' => 'Zugferd',
        'DE' => 'Zugferd',
    ];
    protected static array $description = [
        'EN' => 'Access and manage Zugferd files.',
        'DE' => 'Zugferd-Dateien zugreifen und verwalten.',
    ];
}
