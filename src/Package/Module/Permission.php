<?php
namespace Package\Raxon\Account\Module;

use Doctrine\ORM\Exception\ORMException;
use Entity\User as EntityUser;
use Exception;
use Raxon\App;
use Raxon\Doctrine\Module\Entity as Module;
use Raxon\Exception\AuthorizationException;
use Raxon\Exception\ErrorException;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\Controller;
use Raxon\Module\Database;
use Raxon\Module\File;
use Raxon\Module\Parse;
use Raxon\Module\Validate;
use Raxon\Node\Module\Node;

class Permission
{

    const SCOPE_SYSTEM = 'system';
    const SCOPE_USER = 'user';
    const SCOPE_PRIVATE = 'private';
    const SCOPE_PUBLIC = 'public';

    const CACHE_TIME = 20;  //minutes

    const API = 'system';

    public static function has(EntityUser $user, $name): bool
    {
        $user_permissions = [];
        foreach($user->getRoles() as $role){
            ddd($role);
            $permissions = $role->getPermissions();
            if(
                $permissions &&
                is_array($permissions)
            ){
                foreach($permissions as $permission){
                    $user_permissions[] = $permission->getName();
                }
            }
        }
        if(in_array($name, $user_permissions)){
            return true;
        }
        return false;
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public static function get(App $object, $entity='', $attribute=''){
        $dir = $object->config('project.dir.source') . 'Permission' . $object->config('ds');
        $url = $dir . $entity . $object->config('extension.json');
        if(!File::exist($url)){
            $explode = explode('.', $entity);
            if(array_key_exists(1, $explode)){
                $attribute = explode($entity, $attribute, 2);
                $explode = array_reverse($explode);
                $entity = implode('.', $explode);
                $attribute[0] = $entity;
                $attribute = implode('', $attribute);
                $url = $dir . $entity . $object->config('extension.json');
                if(!File::exist($url)){
                    throw new Exception('Permission url (' . $url . ') not found for entity: ' . $entity);
                }
            } else {
                throw new Exception('Permission url (' . $url . ') not found for entity: ' . $entity);
            }
        }
        $data = $object->data_read($url);
        if($data){
            $get = $data->get($attribute);
            if(empty($get)){
                throw new Exception('Cannot find attribute (' . $attribute .') in entity: ' . $entity);
            }
            return $get;
        }
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws \Doctrine\DBAL\Exception
     * @throws ORMException
     * @throws \Doctrine\ORM\ORMException
     * @throws Exception
     */
    public static function getAccessControl(App $object, $entity=null, $action=''): array
    {
        return [];
        $access_control = $object->config('access_control');
        if(!is_array($access_control)){
            $parse = new Parse($object, $object->data());
            $access_control = $parse->compile($access_control, $object->data());
            $object->config('access_control', $access_control);
        }
        $roles = [];
        $entity = 'Role';
        $entityManager = Database::entityManager($object, ['name'=> Permission::API]);
        $repository = $entityManager->getRepository($object->config('doctrine.entity.prefix') . $entity);
        if(is_array($access_control)){
            foreach($access_control as $access){
                if(
                    property_exists($access, 'entity') &&
                    property_exists($access, 'action') &&
                    property_exists($access, 'roles') &&
                    $access->entity === $entity &&
                    $access->action === $action
                ){
                    foreach($access->roles as $name){
                        $role = $repository->findOneBy([
                            'name' => $name
                        ]);
                        if($role){
                            $roles[] = $role;
                        }
                    }
                    break;
                }
            }
        }
        return $roles;
    }

    /**
     * @throws ObjectException
     * @throws ErrorException
     * @throws \Doctrine\DBAL\Exception
     * @throws ORMException
     * @throws \Doctrine\ORM\ORMException
     * @throws FileWriteException
     * @throws Exception
     */
    public static function controller(App $object, $controller=null, $action='', &$user=null): ?object
    {
        $controller = str_replace('.', ':', Controller::name($controller));
        $action = strtolower(Controller::name($action));
        $url = false;
        $role = false;
        $has_role = false;
        $has_permission = false;
        try {
            $key = $object->request('key');
            if($key){
                $user = User::get_by_key($object);
                if($user){
                    $object->config('user', $user);
                    $user = User::expose($object, $user, 'current');
                }
            }
            if(array_key_exists('HTTP_AUTHORIZATION', $_SERVER)){
                if(!$user){
                    $user = User::get_by_authorization($object);
                    if($user){
                        $object->config('user', $user);
                        $user = User::expose($object, $user, 'current');
                    }
                }
            }
            $uuid = $object->request('user.uuid');
            ddd($uuid);
            if(Core::is_uuid($uuid)){
                if(!$user){
                    $user = User::get_by_uuid($object);
                    if($user){
                        $object->config('user', $user);
                        $user = User::expose($object, $user, 'current');
                    }
                }
            }
            $roles = $user->role ?? [];
            foreach($roles as $role){
                if(!property_exists($role, 'permission')){
                    continue;
                }
                foreach($role->permission as $permission){
                    if(
                        $has_role === false &&
                        property_exists($permission, 'name') &&
                        $permission->name === $controller . ':' . $action
                    ){
                        $has_permission = true;
                        if(
                            property_exists($role, 'name') &&
                            property_exists($role,'rank')
                        ){
                            $has_role = $role;
                            break 2;
                        }
                    }
                }
            }
        } catch (Exception $exception){
            if(!$user){
                $class = 'Account.Role';
                $node = new Node($object);
                $response = $node->record($class, $node->role_system(), [
                    'filter' => [
                        'name' => 'ROLE_ANONYMOUS'
                    ],
                    'relation' => true
                ]);
                if(
                    is_array($response) &&
                    array_key_exists('node', $response) &&
                    property_exists($response['node'], 'uuid') &&
                    property_exists($response['node'], 'permission') &&
                    is_array($response['node']->permission)
                ){
                    foreach($response['node']->permission as $permission){
                        if(property_exists($permission, 'name')){
                            if($permission->name === $controller . ':' . $action){
                                return $response['node'];
                            }
                        }
                    }
                }
//                throw new ErrorException('Need permission ('. $controller .'.' . $action .')...');
                throw new AuthorizationException('You don\'t have permission to access this resource. (' . $controller . ':' . $action . ')' . PHP_EOL . (string) $exception);
            }
        }
        if($has_permission && $has_role){
            return $has_role;
        }
        throw new AuthorizationException('You don\'t have permission to access this resource. (' . $controller . ':' . $action . ')');
    }

    /**
     * @throws ObjectException
     * @throws ORMException
     * @throws AuthorizationException
     * @throws FileWriteException
     * @throws Exception
     */
    public static function request(App $object, $entity=null, $action='', object|null &$role=null, object|null &$user=null, &$fetchJoinCollection=null): array
    {
        $roles = Permission::getAccessControl($object, $entity, $action);
        $response = User::current($object);
        $user = $response['node'] ?? null;
        if($user){
            $session = $object->session('user');
            if($session){
                $roles = $object->session('user.roles');
            }
        }
        if(empty($roles)){
            $roles = $user['role'] ?? [];
        }
        $has_permission = false;
        $request = [];
        $required_attribute = [];
        $explode = explode('.', $entity);
        $explode = array_reverse($explode);
        $alternate = implode('.', $explode);
        foreach($roles as $role){
            if(
                is_array($role) &&
                array_key_exists('permission', $role)
            ){
                $permissions = $role['permission'];
                $role = Core::object($role, Core::OBJECT);
            } elseif(
                is_object($role) &&
                property_exists($role, 'permission')
            ){
                $permissions = $role->permission;
            }
            foreach($permissions as $permission){
                if(
                    is_array($permission) &&
                    array_key_exists('name', $permission)
                ){
                    $name = $permission['name'];
                }
                elseif(
                    is_object($permission) &&
                    property_exists($permission, 'name')
                ) {
                    $name = $permission->name;
                }
                if(
                    (
                        $name === $entity . ':' . $action &&
                        $has_permission === false
                    ) ||
                    (
                        $name === $alternate . ':' . $action &&
                        $has_permission === false
                    )
                ){
                    $has_permission = true;
                    $fetchJoinCollection = true;

                    $attributes = [];
                    //with-input
                    $expose = Module::expose_get(
                        $object,
                        $entity,
                        $entity . '.' . $action . '.input'
                    );
                    foreach($expose as $expose_nr => $expose_value){
                        if(
                            property_exists($expose_value, 'role') &&
                            $expose_value->role === $role->name &&
                            property_exists($expose_value, 'property')
                        ){
                            $attributes = $expose_value->property;
                            break;
                        }
                    }
                    if (
                        !empty($attributes) &&
                        is_array($attributes)
                    ) {
                        foreach ($attributes as $attribute) {
                            $assertion = $attribute;
                            $explode = explode(':', $attribute, 2);
                            $compare = null;
                            if (array_key_exists(1, $explode)) {
                                $compare = $explode[1];
                                $attribute = $explode[0];
                                $is_optional = false;
                                if(substr($attribute,0, 1) === '?'){
                                    $is_optional = true;
                                    $attribute = substr($attribute, 1);
                                } else {
                                    $required_attribute[] = $attribute;
                                }
                                if ($compare) {
                                    $parse = new Parse($object, $object->data());
                                    $compare = $parse->compile($compare, $object->data());
                                    $value = Permission::castValue($object->request($attribute));
                                    if($is_optional){
                                        if(
                                            $value &&
                                            $value === $compare
                                        ){
                                            $request[$attribute] = $compare;
                                        }
                                        if(
                                            $value &&
                                            $value !== $compare
                                        ){
                                            throw new Exception('Assertion failed: ' . $assertion);
                                        }
                                    } else {
                                        if ($value !== $compare) {
                                            throw new Exception('Assertion failed: ' . $assertion);
                                        }
                                        $request[$attribute] = $compare;
                                    }
                                }
                            } else {
                                $is_optional = false;
                                if(substr($attribute,0, 1) === '?'){
                                    $is_optional = true;
                                    $attribute = substr($attribute, 1);
                                } else {
                                    $required_attribute[] = $attribute;
                                }
                                $value = $object->request($attribute);
                                if($is_optional){
                                    if($value){
                                        $request[$attribute] = $value;
                                    }
                                } else{
                                    $request[$attribute] = $value;
                                }
                            }
                        }
                        break 2;
                    }
                }
            }
        }
        if(empty($has_permission)){
            $logger = $object->config('project.log.security');
            if($logger){
                $object->logger($logger)->info('You don\'t have permission to access this resource. (' . $entity . ':' . $action . ')');
            } else {
                $logger = $object->config('project.log.app');
                if($logger){
                    $object->logger($logger)->info('You don\'t have permission to access this resource. (' . $entity . ':' . $action . ')');
                }
            }
            d($entity);
            ddd($action);
            throw new AuthorizationException('You don\'t have permission to access this resource. (' . $entity . ':' . $action . ')');
        }
        $missing_attribute = [];
        foreach($required_attribute as $attribute){
            $value = $object->request($attribute);
            if($value === null){
                $missing_attribute[] = $attribute;
            }
        }
        if(!empty($missing_attribute)){
            throw new Exception('Method requires attributes [' . implode(', ', $missing_attribute) . '].');
        }
        foreach($request as $attribute => $value){
            $object->request($attribute, $value);
        }
        return $request;
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    protected static function validate(App $object, $url, $type){
        $data = $object->data(sha1($url));
        if($data === null){
            $data = $object->parse_read($url, sha1($url));
        }
        if($data){
            $validate = $data->data($type . '.validate');
            if(empty($validate)){
                return false;
            }
            return Validate::validate($object, $validate);
        }
        return false;
    }

    /**
     * @throws ObjectException
     */
    protected static function castValue($array=[]): mixed
    {
        if(is_array($array)){
            foreach($array as $key => $value) {
                if(is_object($value) || is_array($value)){
                    $array[$key] = Permission::castValue($value);
                } else {
                    if($value === 'null'){
                        $array[$key] = null;
                    }
                    elseif($value === 'true'){
                        $array[$key] = true;
                    }
                    elseif($value === 'false'){
                        $array[$key] = false;
                    }
                    elseif(is_numeric($value)){
                        $array[$key] = $value + 0;
                    }
                    elseif(substr($value, 0, 1) === '[' && substr($value, -1, 1) === ']'){
                        $array[$key] = Core::object($value, Core::OBJECT_ARRAY);
                    }
                }
            }
            return $array;
        }
        elseif(is_object($array)){
            foreach($array as $key => $value) {
                if(is_object($value) || is_array($value)){
                    $array->$key = Permission::castValue($value);
                } else {
                    if($value === 'null'){
                        $array->$key = null;
                    }
                    elseif($value === 'true'){
                        $array->$key = true;
                    }
                    elseif($value === 'false'){
                        $array->$key = false;
                    }
                    elseif(is_numeric($value)){
                        $array->$key = $value + 0;
                    }
                    elseif(substr($value, 0, 1) === '[' && substr($value, -1, 1) === ']'){
                        $array->$key = Core::object($value, Core::OBJECT_ARRAY);
                    }
                }
            }
            return $array;
        }
        elseif($array === 'null'){
            return null;
        }
        elseif($array === 'true'){
            return true;
        }
        elseif($array === 'false'){
            return false;
        }
        elseif(is_numeric($array)){
            return $array + 0;
        }
        elseif(substr($array, 0, 1) === '[' && substr($array, -1, 1) === ']'){
            return Core::object($array, Core::OBJECT_ARRAY);
        }
        else {
            return $array;
        }
    }
}