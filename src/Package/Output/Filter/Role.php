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
            foreach ($response as $nr => $object){
                if(is_array($object)){
                    if(array_key_exists('password', $object)){
                        $response[$nr]['password'] = '[redacted]';
                    }
                }
                elseif(is_object($object)){
                    if(property_exists($object, 'password')){
                        $object->password = '[redacted]';
                    }
                }
            }
        }
        elseif(is_object($response)){
            foreach ($response as $nr => $object){
                if(is_array($object)){
                    if(array_key_exists('password', $object)){
                        $response[$nr]['password'] = '[redacted]';
                    }
                }
                elseif(is_object($object)){
                    if(property_exists($object, 'password')){
                        $object->password = '[redacted]';
                    }
                }
            }
        }
        ddd($response);
        return $response;
    }

}