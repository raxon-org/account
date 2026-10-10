<?php

/**
 * @author          Remco van der Velde
 * @since           2020-09-18
 * @copyright       Remco van der Velde
 * @license         MIT
 * @version         1.0
 * @changeLog
 *     -            all
 */


use Raxon\App;

use Raxon\Module\Controller;
use Raxon\Module\Data;
use Raxon\Module\Filter;

use Raxon\Node\Module\Node;

/**
 * @throws Exception
 */
function validate_user_is_active(App $object, object $record, mixed $value='', mixed $attribute='', mixed $validate='', mixed $function=false): bool
{
    $dir_node = $object->config('project.dir.node');
    $data = new Data($record);
    if(
        is_object($value) &&
        property_exists($value, 'attribute') &&
        is_string($value->attribute)
    ){
        $attribute = $value->attribute;
    }
    $uuid = $data->get($attribute);
    $class = 'Account.User';
    $node = new Node($object);
    $read = $node->read($class, $node->role_system(), ['uuid' => $uuid]);
    $user = $read['node'] ?? null;
    if($user){
        if(
            property_exists($user, 'is') &&
            property_exists($user->is, 'active') &&
            $user->is->active >= 1
        ){
            if(
                property_exists($user->is, 'deleted') &&
                !empty($user->is->deleted)
            ){
                return false;
            }
            return true;
        }
    }
    return false;
}
