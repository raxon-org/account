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
    d($value);
    d($attribute);
    d($validate);
    ddd($data);
    $uuid = $data->get('uuid');
    $class = 'Account.User';
    $url = $dir_node . 'Data' . $object->config('ds') . $class . $object->config('extension.json');
    $node = new Node($object);
    $user = $node->read($class, $node->role_system(), ['uuid' => $uuid]);
    ddd($user);
    return false;
}
