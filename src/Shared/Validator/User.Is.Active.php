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
    $user = $node->read($class, $node->role_system(), ['uuid' => $uuid]);
    if($user && is_object($user['node'])){
        if(
            property_exists($user['node'], 'is') &&
            property_exists($user['node']->is, 'active') &&
            $user['node']->is->active === true
        ){
            if(
                property_exists($user['node']->is, 'deleted') &&
                !empty($user['node']->is->deleted)
            ){
                return false;
            }
            return true;
        }
    }
    return false;
}
