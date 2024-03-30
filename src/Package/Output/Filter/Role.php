<?php

namespace Package\R3m\Io\Account\Output\Filter;

use R3m\Io\App;

use R3m\Io\Module\Controller;

class Role extends Controller {

    const DIR = __DIR__ . '/';

    public static function permission(App $object, $response=null): array
    {
        return $response;
        $result = [];
        if(
            !empty($response) &&
            is_array($response)
        ){
            foreach($response as $nr => $record){
                if(
                    is_object($record) &&
                    property_exists($record, 'name')
                ){
                    $result[] = $record->name;
                }
            }
        }
        return $result;
    }
}