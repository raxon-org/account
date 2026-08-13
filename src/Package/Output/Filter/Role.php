<?php

namespace Package\Raxon\Account\Output\Filter;

use Raxon\App;

use Raxon\Module\Controller;

class Role extends Controller {

    const DIR = __DIR__ . '/';

    public static function permission(App $object, $response=null): array
    {
        //permission array should stay intact
        return $response;
    }

    public static function user(App $object, $response=null): array
    {
        //permission array should stay intact
        if(is_array($response)){
            foreach ($response as $key => $value){
                if(strtolower($key, 'password')){
                    $response[$key] = '[redacted]';
                }
            }
        }
        elseif(is_object($response)){
            foreach ($response as $key => $value){
                if(strtolower($key, 'password')){
                    $response->{$key} = '[redacted]';
                }
            }
        }
        return $response;
    }

}