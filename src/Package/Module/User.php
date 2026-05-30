<?php
namespace Package\Raxon\Account\Module;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;

use DateTime;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;

use Entity\User as Entity;

use Exception;

use Raxon\App;

use Raxon\Module\Core;
use Raxon\Module\File;
use Raxon\Module\Handler;

use Raxon\Doctrine\Module\Database;

use Raxon\Node\Module\Node;

use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Exception\AuthorizationException;
use Raxon\Exception\ErrorException;

class User
{
    const BLOCK_EMAIL_COUNT = 5;
    const BLOCK_PASSWORD_COUNT = 5;

    /**
     * @throws NonUniqueResultException
     * @throws ErrorException
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function login(App $object, object $input): void
    {
        if(!property_exists($input, 'email')){
            throw new ErrorException('E-mail is required.');
        }
        if(!property_exists($input, 'password')){
            throw new ErrorException('Password is required.');
        }
        $config = Database::config($object);
        $connection = $object->config('doctrine.environment.system.*');
        if($connection === null){
            throw new ErrorException('Connection not configured.');
        }
        $connection->manager = Database::entity_manager($object, $config, $connection);
        if(User::is_blocked($object, $input, $connection) === false){
            $repository = $connection->manager->getRepository(Entity::class);
            $node = $repository->findOneBy([
                'email' => $input->email,
                'isActive' => 1
            ]);
            if($node) {
                $password = $input->password;
                $verify = password_verify($password, $node->getPassword());
                if($verify === false){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    $input->status = UserLogger::STATUS_INVALID_EMAIL_PASSWORD;
                    UserLogger::log($object, $input, $node, $connection);
                    throw new ErrorException('Invalid e-mail-password.');
                }
                $input->status = UserLogger::STATUS_SUCCESS;
                UserLogger::log($object, $input, $node, $connection);
                $user = User::expose($object, $node, __FUNCTION__);
                $user->token = User::get_token($object, $node);
                $user->refreshToken = User::get_refresh_token($object, $node);
                $encrypted_refreshToken = sha1($user->refreshToken);
                $repository = $connection->manager->getRepository(Entity::class);
                $cost = 13;
                $node->setRefreshToken(
                    password_hash(
                        $encrypted_refreshToken,
                        PASSWORD_BCRYPT,
                        [
                            'cost' => $cost
                        ]
                    )
                );
                $node->setIsLoggedIn(new DateTime());
                $node->setKey(Core::uuid() . '-' . Core::uuid());
                $connection->manager->persist($node);
                $connection->manager->flush();
                $user->isLoggedIn = $node->getIsLoggedIn();
                $user->isUpdated = $node->getIsUpdated();
                $user->key = $node->getKey();
//                $data = [];
//                $data['node'] = $user;
                Handler::cookie([
                    'value' => $user->token,
                    'parameters' => [
                        'expires' => strtotime('+3 minute'),
                        'path' => '/',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict',
                        'domain' => $_SERVER['HTTP_HOST'],
                    ]
                ]);
//                return $data;
            } else {
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                $input->status = UserLogger::STATUS_INVALID_EMAIL_PASSWORD;
                UserLogger::log($object, $input, null, $connection);
                throw new ErrorException('Invalid e-mail-password.');
            }
        } else {
            $status = 401;
            Handler::header('Status: ' . $status, $status, true);
            $input->status = UserLogger::STATUS_BLOCKED;
            UserLogger::log($object, $input, null, $connection);
            throw new ErrorException('User blocked for:.');
        }
    }

    /**
     * @throws AuthorizationException
     * @throws ObjectException
     * @throws Exception
     */
    public static function expose(App $object, Entity $record, string $function): object
    {
        $node = new Node($object);
        $class = 'Account.Role';
        $role = $node->role_system();
        $response = $node->list(
            $class,
            $role,
            [
                'filter' => [
                    'name' => 'ROLE_USER'
                ],
                'relation' => true
            ]
        );
        $role = $response['list'][0] ?? false;
        if($role === false){
            throw new Exception('Role ROLE_USER not found.');
        }
        $entity = 'User';
        $expose = \Raxon\Doctrine\Module\Entity::expose_get(
            $object,
            $entity,
            $entity . '.' . $function . '.output'
        );
        $node = $record;
        $record = [];
        $record = \Raxon\Doctrine\Module\Entity::output(
            $object,
            $node,
            $expose,
            $entity,
            $function,
            $record,
            $role
        );
        return (object) $record;
    }

    /**
     * @throws FileWriteException
     * @throws ObjectException
     * @throws Exception
     */
    private static function get_token(App $object, Entity $node): string
    {
        $configuration = Jwt::configuration($object);
        $options = [];
        $options['user'] = $node;
        $token = Jwt::get($object, $configuration, $options);
        $string = $token->toString();
        $url = $object->config('project.dir.data') . 'Account/Jwt.json';
        $cache = $object->data(App::CACHE);
        $config = $cache->get(sha1($url));
        $crypt_url = $config->get('token.crypt_url') ?? null;
        if($crypt_url){
            //if you want you can logout everyone from the system by changing the content of crypt_url
            $key = Core::key($crypt_url);
            $crypt_string = Crypto::encrypt($string, $key); //around: 1800 chars fits in the 4KB cookie
//            $crypt_compressed = gzencode($crypt_string, 9); //around 1000 chars //json cant handle this data
            return $crypt_string;
            //return steps
            /*
            $crypt_decompressed = gzdecode($crypt_compressed);
            $decrypt_string = Crypto::decrypt($crypt_decompressed, $key); //around: 1800 chars fits in the 4KB cookie
            d($decrypt_string);
            */
        } else {
            throw new Exception('property token.crypt_url not set in data/Account/Jwt.json.');
        }
    }

    /**
     * @throws FileWriteException
     * @throws ObjectException
     */
    private static function get_refresh_token(App $object, Entity $node): string
    {
        $configuration = Jwt::configuration($object);
        $options = [];
        $options['user'] = $node;
        $options['refresh'] = true;
        $token = Jwt::refresh_get($object, $configuration, $options);
        return $token->toString();
    }

    /**
     * @throws Exception
     */
    private static function getTokens(App $object, object $input, Entity\User $node): array
    {
        $configuration = Jwt::configuration($object);
        $options = [];
        $options['user'] = $node;
        $token = Jwt::get($object, $configuration, $options);
        $token = $token->toString();
        $options['refresh'] = true;
        $configuration = Jwt::configuration($object, $options);
        $refreshToken = Jwt::refresh_get($object, $configuration, $options);
        $refreshToken = $refreshToken->toString();
        ddd('fix refresh after the general javascript function which handles the refresh token ');
//        $record->token = $token;
//        $record->refresh_token = $refreshToken;
        /*
        $node = new Node($object);
        $node->patch(
            'Account.User',
            $node->role_system(),
            [
                'uuid' => $record->uuid,
                'refresh_token' => $encrypted_refreshToken
            ]
        );
        */
//        return $record;
        return [];
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
    public static function is_blocked(App $object, object $input, object|null $connection=null): bool
    {
        if(!property_exists($input, 'email')){
            throw new ErrorException('E-mail is required.');
        }
        if($connection === null){
            $config = Database::config($object);
            $connection = $object->config('doctrine.environment.system.*');
            $connection->manager = Database::entity_manager($object, $config, $connection);
        }
        $repository = $connection->manager->getRepository(Entity::class);
        $node = $repository->findOneBy(['email' => $input->email]);
        if($node){
            $old_status = $input->status ?? null;
            $input->status = UserLogger::STATUS_INVALID_EMAIL_PASSWORD;
            $count = UserLogger::count($object, $input, $node, $connection);
            if($count >= User::BLOCK_PASSWORD_COUNT){
                $input->status = UserLogger::STATUS_BLOCKED;
                UserLogger::log($object, $input, $node, $connection);
                return true;
            }
            if($old_status){
                $input->status = $old_status;
            } else {
                unset($input->status);
            }
        } else {
            $old_status = $input->status ?? null;
            $input->status = UserLogger::STATUS_INVALID_EMAIL_PASSWORD;
            $count = UserLogger::count($object, $input, null, $connection);
            if($count >= User::BLOCK_EMAIL_COUNT){
                $input->status = UserLogger::STATUS_BLOCKED;
                UserLogger::log($object, $input, $node, $connection);
                return true;
            }
            if($old_status){
                $input->status = $old_status;
            } else {
                unset($input->status);
            }
        }
        return false;
    }

    /**
     * @throws FileWriteException
     * @throws ObjectException
     * @throws ErrorException
     */
    public static function token(App $object, $email=''): string
    {
        //get the user from the node list System.User with email=email
        $configuration = Jwt::configuration($object);
        $class = 'Account.User';
        $node = new Node($object);
        $record = $node->record(
            $class,
            $node->role_system(),
            [
                'where' => [
                    [
                        'value' => $email,
                        'attribute' => 'email',
                        'operator' => '==='
                    ],
                    [
                        'value' => 1,
                        'attribute' => 'is.active',
                        'operator' => '>='
                    ]
                ],
                'relation' => true
            ]
        );
        if(!$record || !array_key_exists('node', $record)){
            throw new ErrorException('User not found.');
        }

        $options = [];
        $options['user'] = $record['node'];
        $token = Jwt::get($object, $configuration, $options);
        $token = $token->toString();
        return $token;
    }

    /**
     * @throws ObjectException
     * @throws AuthorizationException
     * @throws FileWriteException
     * @throws Exception
     */
    public static function current(App $object): array
    {
        $user = User::get_by_authorization($object);
        $node = User::expose($object, $user, __FUNCTION__);
        $data = [];
        $data['node'] = $node;
        return $data;
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
        ddd($token_unencrypted);
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
                $config = Database::config($object);
                $connection = $object->config('doctrine.environment.system.*');
                $em = Database::entity_manager($object, $config, $connection);
                $repository = $em->getRepository(Entity::class);
                $item = $repository->findOneBy([
                    'uuid' => $uuid,
                    'email' => $email
                ]);
                if(empty($item->getIsActive())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('User is not active...');
                }
                if(!empty($item->getIsDeleted())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('User is deleted...');
                }
                if(empty($item->getRole())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('User has no roles...');
                }
                $refreshToken = sha1($token);
                if(!password_verify($refreshToken, $item->getRefreshToken())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Refresh token not valid...');
                }
                $array = User::getTokens($object, $item);
                $data = [];
                $data['node'] = $array;
                return $data;
            }
        }
        $status = 401;
        Handler::header('Status: ' . $status, $status, true);
        throw new AuthorizationException('Authentication failure... (invalid claim)');
    }

    /**
     * @throws ObjectException
     * @throws \Doctrine\ORM\ORMException
     * @throws ORMException
     * @throws \Doctrine\DBAL\Exception
     * @throws FileWriteException
     * @throws Exception
     */
    public static function get_by_key(App $object): null|Entity{
        $item = $object->config('user');
        if($item){
            return $item;
        } else {
            $key = $object->request('key');
            if(!$key){
                return null;
            }
            $em = $object->config('doctrine.em');
            if($em === null){
                $config = Database::config($object);
                $connection = $object->config('doctrine.environment.system.*');
                $em = Database::entity_manager($object, $config, $connection);
                $object->config('doctrine.em', $em);
            }

            $repository = $em->getRepository(Entity::class);
            $item = $repository->findOneBy(['key' => $key]);
        }
        if($item){
            if(empty($item->getIsActive())){
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                throw new AuthorizationException('Account is not active.');
            }
            if(!empty($item->getIsDeleted())){
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                throw new AuthorizationException('Account is deleted.');
            }
            if(empty($item->getRole())){
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                throw new AuthorizationException('Account has no roles.');
            }
            $item->setIsLoggedIn(new DateTime());
            $em->persist($item);
            $em->flush();
            $object->config('user', $item);
            return $item;
        }
        return null;
    }

    /**
     * @throws AuthorizationException
     * @throws Exception
     */
    public static function get_by_uuid(App $object): null|Entity
    {
        $item = $object->config('user');
        if($item){
            return $item;
        } else {
            $uuid = $object->request('user.uuid');
            if(!$uuid){
                return null;
            }
            $em = $object->config('doctrine.em');
            if($em === null){
                $config = Database::config($object);
                $connection = $object->config('doctrine.environment.system.*');
                $em = Database::entity_manager($object, $config, $connection);
                $object->config('doctrine.em', $em);
            }

            $repository = $em->getRepository(Entity::class);
            $item = $repository->findOneBy(['uuid' => $uuid]);
        }
        if($item){
            if(empty($item->getIsActive())){
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                throw new AuthorizationException('Account is not active.');
            }
            if(!empty($item->getIsDeleted())){
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                throw new AuthorizationException('Account is deleted.');
            }
            if(empty($item->getRole())){
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                throw new AuthorizationException('Account has no roles.');
            }
            $item->setIsLoggedIn(new DateTime());
            $em->persist($item);
            $em->flush();
            $object->config('user', $item);
            return $item;
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
    public static function get_by_authorization_old(App $object): mixed
    {
        $item = User::token_id($object);
        ddd($item);
        $node = new Node($object);
        $class = 'Account.Role';
        $response = $node->record(
            $class,
            $node->role_system(),
            [
                'filter' => [
                    'name' => 'ROLE_USER'
                ],
                'relation' => true
            ]
        );
        $role = $response['node'] ?? null;
        $entity = 'User';
        $function = 'current';

        $toArray = \Raxon\Doctrine\Module\Entity::expose_get(
            $object,
            $entity,
            $entity . '.' . $function . '.output'
        );
        $record = [];
        $record = \Raxon\Doctrine\Module\Entity::output(
            $object,
            $item,
            $toArray,
            $entity,
            $function,
            $record,
            $role
        );
        $item->setRole($record['role']);
        $object->set('user', $item);
        return $item;
    }

    public static function get_by_authorization(App $object): null|Entity
    {
        $item = $object->config('user');
        if($item){
            return $item;
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
        $url = $object->config('project.dir.data') . 'Account/Jwt.json';
        $config = $object->parse_read($url, sha1($url));
        $crypt_url = $config->get('token.crypt_url') ?? null;
        $token = substr($token , 7);
        if($crypt_url) {
//            $crypt_decompressed = gzdecode($token);
            //if you want you can logout everyone from the system by changing the content of crypt_url
            $key = Core::key($crypt_url);
            $token = Crypto::decrypt($token, $key); //around: 1800 chars fits in the 4KB cookie
        }
        if(!$token){
            $status = 401;
            Handler::header('Status: ' . $status, $status, true);
            throw new AuthorizationException('Please provide a valid token...');
        }
        $token_unencrypted = Jwt::decryptToken($object, $token);
        $claims = $token_unencrypted->claims();
        if($claims->has('user')) {
            $user = $claims->get('user');
            $em = $object->config('doctrine.em');
            if($em === null){
                $config = Database::config($object);
                $connection = $object->config('doctrine.environment.system.*');
                $em = Database::entity_manager($object, $config, $connection);
                $object->config('doctrine.em', $em);
            }
            $repository = $em->getRepository('\\Entity\\User');
            $item = $repository->findOneBy([
                'uuid' => $user['uuid'],
                'email' => $user['email'],
            ]);
            if($item) {
                if(empty($item->getIsActive())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Account is not active.');
                }
                if(!empty($item->getIsDeleted())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Account is deleted.');
                }
                if(empty($item->getRole())){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Account has no roles.');
                }
                $item->setIsLoggedIn(new DateTime());
                $em->persist($item);
                $em->flush();
                $object->config('user', $item);
                return $item;
            }
        }
        return null;
    }

}