<?php

namespace Package\R3m\Io\Task\Controller;

use R3m\Io\App;

use R3m\Io\Module\Controller;

class Status extends Controller {
    const DIR = __DIR__ . '/';
    const MODULE_INFO = 'Info';


    public static function youtube(App $object)
    {
        ddd($object->request());
    }
}