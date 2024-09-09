<?php
namespace Domain\Api_Workandtravel_World\Service;

use Doctrine\ORM\Exception\NotSupported;
use Repository\PermissionRepository;
use stdClass;
use DateTime;

use Entity\Role;
use Entity as Entity_class;
use Entity\User as Entity;

use Raxon\Org\App;

use Raxon\Org\Module\Core;
use Raxon\Org\Module\Data;
use Raxon\Org\Module\Database;
use Raxon\Org\Module\Handler;
use Raxon\Org\Module\Response;

use Exception;

use Raxon\Org\Exception\FileWriteException;
use Raxon\Org\Exception\ObjectException;
use Raxon\Org\Exception\AuthorizationException;
use Raxon\Org\Exception\ErrorException;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;

class User extends Main
{
    const BLOCK_EMAIL_COUNT = 5;
    const BLOCK_PASSWORD_COUNT = 5;

    /**
     * @throws ObjectException
     */
    public static function has_permission(App $object, Entity $user, $name): bool
    {
        $data = false;
        $user_permissions = [];
        $uuid = $user->getUuid();
        $localStorage = $object->config('project.dir.data') . 'User' . $object->config('ds') . $uuid . $object->config('extension.json');
        if(File::exist($localStorage)){
            $mtime = File::mtime($localStorage);
            $data = $object->data_read($localStorage, sha1($localStorage));
            if($data){
                $user_permissions = $data->get('permission');
            }
            $duration = strtotime('+20 Minutes');
            dd($duration);
        }
        if(empty($user_permissions)){
            foreach($user->getRoles() as $role){
                $permissions = $role->getPermissions();
                if($permissions){
                    foreach($permissions as $permission){
                        $user_permissions[] = $permission->getName();
                    }
                }
            }
        }
        if($user_permissions){
            if(!$data){
                $data = new Data();
                $data->set('user.permission', $user_permissions);
            }
            $data->write($localStorage);
        }
        if(in_array($name, $user_permissions)){
            return true;
        }
        return false;
    }

    /**
     * @throws ObjectException
     * @throws \Doctrine\ORM\ORMException
     * @throws ORMException
     * @throws FileWriteException
     * @throws \Doctrine\DBAL\Exception
     */
    public static function getById(App $object, $id=null){
        if($id !== null){
            $entityManager = Database::entityManager($object, [
                'name' => Main::API
            ]);
            $repository = $entityManager->getRepository(Entity::class);
            return $repository->findOneBy([
                'id' => $id,
            ]);
        }
        return false;
    }

    /**
     * @throws ObjectException
     * @throws \Doctrine\ORM\ORMException
     * @throws ORMException
     * @throws \Doctrine\DBAL\Exception
     * @throws FileWriteException
     */
    public static function getByKey(App $object){
        $key = $object->request('key');
        if(!$key){
            return null;
        }
        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $repository = $entityManager->getRepository(Entity::class);
        $node = $repository->findOneBy(['key' => $key]);
        if($node){
            $node->fetchByKey(true);
            return $node;
        }
        return null;

    }

    /**
     * @throws AuthorizationException
     * @throws ObjectException
     * @throws FileWriteException
     * @throws ORMException
     * @throws \Doctrine\ORM\ORMException
     * @throws Exception
     */
    public static function getByAuthorization(App $object){
        $object->logger()->info('getByAuthorization need session from backend.');
        $node = $object->get('user');
        if(!empty($node)){
            return $node;
        }
        $token = '';
        if($object->request('authorization')){
            $token = $object->request('authorization');
        }
        elseif($object->data(App::REQUEST_HEADER . '.' . 'Authorization')){
            $token = $object->data(App::REQUEST_HEADER . '.' . 'Authorization');
        }
        elseif(array_key_exists('HTTP_AUTHORIZATION', $_SERVER)){
            $token = $_SERVER['HTTP_AUTHORIZATION'];
        }
        elseif(array_key_exists('REDIRECT_HTTP_AUTHORIZATION', $_SERVER)){
            $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        $token = substr($token , 7);
        if(!$token){
            $status = 401;
            Handler::header('Status: ' . $status, $status, true);
            throw new AuthorizationException('Please provide a valid token...');
        }
        $token_unencrypted = Jwt::decryptToken($object, $token);
        $claims = $token_unencrypted->claims();
        if($claims->has('user')) {
            $user = $claims->get('user');
            $entityManager = Database::entityManager($object);
            $repository = $entityManager->getRepository('\\Entity\\User');
            $node = $repository->findOneBy([
                'uuid' => $user['uuid'],
                'email' => $user['email'],
            ]);
            if($node) {
                if(empty($node->getIsActive())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Account is not active.');
                }
                if(!empty($node->getIsDeleted())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Account is deleted.');
                }
                if(empty($node->getRoles())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Account has no roles.');
                }
                $node->setIsLoggedIn(new DateTime());
                $entityManager->persist($node);
                $entityManager->flush();
                $object->set('user', $node);
                return $node;
            }
        }
        return null;
    }

    /**
     * @throws Exception
     */
    private static function getTokens(App $object, Entity $node): array
    {
        $configuration = Jwt::configuration($object);
        $options = [];

        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $entity = $object->config('doctrine.entity.prefix') . 'Role';
        $repository = $entityManager->getRepository($entity);
        $role_name = 'ROLE_ANONYMOUS';
        $role = $repository->findOneBy(['name' => $role_name]);
        $entity = 'User';
        $function = __FUNCTION__;
        $expose = \Raxon\Org\Doctrine\Service\Entity::expose_get(
            $object,
            $entity,
            $entity . '.' . $function . '.output'
        );
        $record = [];
        $record = \Raxon\Org\Doctrine\Service\Entity::output(
            $object,
            $node,
            $expose,
            $entity,
            $function,
            $record,
            $role
        );
        $options['user'] = $record;
        $token = Jwt::get($object, $configuration, $options);
        $token = $token->toString();
        $options['refresh'] = true;
        $configuration = Jwt::configuration($object, $options);
        $refreshToken = Jwt::refresh_get($object, $configuration, $options);
        $refreshToken = $refreshToken->toString();
        $encrypted_refreshToken = sha1($refreshToken);

        $entityManager = Database::entityManager($object, ['name' => Main::API]);
        $repository = $entityManager->getRepository(Entity::class);
        $node = $repository->findOneBy(['id' => $node->getId()]);
        $cost = 13;
        $node->setRefreshToken(password_hash($encrypted_refreshToken, PASSWORD_BCRYPT, [
            'cost' => $cost
        ]));
        $node->setIsLoggedIn(new DateTime());
        $entityManager->persist($node);
        $entityManager->flush();

        $expose = \Raxon\Org\Doctrine\Service\Entity::expose_get(
            $object,
            $entity,
            $entity . '.' . $function . '.output'
        );
        $record = [];
        $record = \Raxon\Org\Doctrine\Service\Entity::output(
            $object,
            $node,
            $expose,
            $entity,
            $function,
            $record,
            $role
        );
        $options['user'] = $record;
        $options['user']['token'] = $token;
        $options['user']['refreshToken'] = $refreshToken;
        return $options['user'];
    }

    /**
     * @throws NonUniqueResultException
     * @throws ErrorException
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function login(App $object): array
    {
        ddd('here we are');
        if(User::is_blocked($object, $object->request('email')) === false){
            $entityManager = Database::entityManager($object);
            $repository = $entityManager->getRepository(Entity::class);
            $node = $repository->findOneBy([
                'email' => $object->request('email'),
                'isActive' => 1
            ]);
            if($node) {
                $password = $object->request('password');
                $verify = password_verify($password, $node->getPassword());
                if(empty($verify)){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    Userlogger::log($object, $node, UserLogger::STATUS_INVALID_PASSWORD);
                    throw new ErrorException('Invalid e-mail-password.');
                }
                Userlogger::log($object, $node, UserLogger::STATUS_SUCCESS);
                $array = User::getTokens($object, $node);
                $data = [];
                $data['node'] = $array;
                return $data;
            } else {
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                Userlogger::log($object, null, UserLogger::STATUS_INVALID_EMAIL);
                throw new ErrorException('Invalid e-mail-password.');
            }
        } else {
            $status = 401;
            Handler::header('Status: ' . $status, $status, true);
            Userlogger::log($object, null, UserLogger::STATUS_BLOCKED);
            throw new ErrorException('User blocked.');
        }
    }

    /**
     * @throws NonUniqueResultException
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws ErrorException
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws FileWriteException
     * @throws ObjectException
     * @throws Exception
     */
    public static function is_blocked(App $object, $email=''): bool
    {
        $entityManager = Database::entityManager($object);
        if(!$entityManager){
            throw new ErrorException('Entity manager not found.');
        }
        $repository = $entityManager->getRepository(Entity::class);
        $node = $repository->findOneBy(['email' => $email]);
        if($node){
            $count = UserLogger::count($object, $node, UserLogger::STATUS_INVALID_PASSWORD);
            if($count >= User::BLOCK_PASSWORD_COUNT){
                Userlogger::log($object, $node, UserLogger::STATUS_BLOCKED);
                return true;
            }
        } else {
            $count = UserLogger::count($object, null, UserLogger::STATUS_INVALID_EMAIL);
            if($count >= User::BLOCK_EMAIL_COUNT){
                Userlogger::log($object, $node, UserLogger::STATUS_BLOCKED);
                return true;
            }
        }
        return false;
    }

    /**
     * @throws AuthorizationException
     * @throws FileWriteException
     * @throws ObjectException
     * @throws NotSupported
     * @throws Exception
     */
    public static function current(App $object): array
    {
        $token = '';
        if(array_key_exists('HTTP_AUTHORIZATION', $_SERVER)){
            $token = $_SERVER['HTTP_AUTHORIZATION'];
        }
        elseif(array_key_exists('REDIRECT_HTTP_AUTHORIZATION', $_SERVER)){
            $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        $token = substr($token , 7);
        if(!$token){
            throw new AuthorizationException('Please provide a valid token...');
        }
        $token_unencrypted = Jwt::decryptToken($object, $token);
        $claims = $token_unencrypted->claims();
        if($claims->has('user')){
            $user =  $claims->get('user');
            $uuid = false;
            $email = false;
            if(array_key_exists('uuid', $user)){
                $uuid = $user['uuid'];
            }
            if(array_key_exists('email', $user)){
                $email = $user['email'];
            }
            if($uuid && $email){
                $entityManager = Database::entityManager($object);
                $repository = $entityManager->getRepository(Entity::class);
                $node = $repository->findOneBy([
                    'uuid' => $uuid,
                    'email' => $email
                ]);
                if(empty($node->getIsActive())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('User is not active...');
                }
                if(!empty($node->getIsDeleted())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('User is deleted...');
                }
                if(empty($node->getRoles())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('User has no roles...');
                }
                $entity = $object->config('doctrine.entity.prefix') . 'Role';
                $repository = $entityManager->getRepository($entity);
                $role_name = 'ROLE_USER';
                $role = $repository->findOneBy(['name' => $role_name]);

                $entity = 'User';
                $function = __FUNCTION__;

                $toArray = \Raxon\Org\Doctrine\Service\Entity::expose_get(
                    $object,
                    $entity,
                    $entity . '.' . $function . '.output'
                );
                $record = [];
                $record = \Raxon\Org\Doctrine\Service\Entity::output(
                    $object,
                    $node,
                    $toArray,
                    $entity,
                    $function,
                    $record,
                    $role
                );
                $data = [];
                $data['node'] = $record;
                return $data;
            }
        }
        $status = 401;
        Handler::header('Status: ' . $status, $status, true);
        throw new AuthorizationException('Authentication failure... (invalid claim)');
    }

    /**
     * @throws Exception
     */
    public static function token(App $object, $email=''): string
    {
        $configuration = Jwt::configuration($object);
        $entityManager = Database::entityManager($object);
        $repository = $entityManager->getRepository(Entity::class);
        $node = $repository->findOneBy([
            'email' => $email
        ]);
        if($node){
            $roles = $node->getRoles();
            $options = [];
            $options['user'] = Core::object_array($node);
            unset($options['user']['parameters']);
            unset($options['user']['roles']);
            unset($options['user']['profile']);
            unset($options['user']['password']);
            unset($options['user']['refreshToken']);
            foreach($roles as $nr => $role){
                $options['user']['roles'][$nr] = $role->getName();
            }
            $token = Jwt::get($object, $configuration, $options);
            return $token->toString() . PHP_EOL;
        }
        $status = 401;
        Handler::header('Status: ' . $status, $status, true);
        throw new Exception('Cannot find node by e-mail: ' . $email);
    }

    /**
     * @throws AuthorizationException
     * @throws Exception
     */
    public static function refresh_token(App $object): array
    {
        $token = '';
        if(array_key_exists('HTTP_AUTHORIZATION', $_SERVER)){
            $token = $_SERVER['HTTP_AUTHORIZATION'];
        }
        elseif(array_key_exists('REDIRECT_HTTP_AUTHORIZATION', $_SERVER)){
            $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        $token = substr($token , 7);
        if(!$token){
            throw new AuthorizationException('Please provide a valid token...');
        }
        $token_unencrypted = Jwt::decryptRefreshToken($object, $token);
        $claims = $token_unencrypted->claims();
        if($claims->has('user')){
            $user =  $claims->get('user');
            $uuid = false;
            $email = false;
            if(array_key_exists('uuid', $user)){
                $uuid = $user['uuid'];
            }
            if(array_key_exists('email', $user)){
                $email = $user['email'];
            }
            if($uuid && $email){
                $entityManager = Database::entityManager($object);
                $repository = $entityManager->getRepository(Entity::class);
                $node = $repository->findOneBy([
                    'uuid' => $uuid,
                    'email' => $email
                ]);
                if(empty($node->getIsActive())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('User is not active...');
                }
                if(!empty($node->getIsDeleted())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('User is deleted...');
                }
                if(empty($node->getRoles())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('User has no roles...');
                }
                $refreshToken = sha1($token);
                if(!password_verify($refreshToken, $node->getRefreshToken())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Refresh token not valid...');
                }
                $array = User::getTokens($object, $node);
                $data = [];
                $data['node'] = $array;
                return $data;
            }
        }
        $status = 401;
        Handler::header('Status: ' . $status, $status, true);
        throw new AuthorizationException('Authentication failure... (invalid claim)');
    }

}