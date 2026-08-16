<?php
namespace Package\Raxon\Account\Module;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use Exception;
use DateTime;
use Raxon\Exception\UrlEmptyException;
use Raxon\App;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Exception\AuthorizationException;
use Raxon\Exception\ErrorException;
use Raxon\Module\Core;
use Raxon\Module\Handler;
use Raxon\Node\Module\Node;
use Throwable;
class User
{
    const BLOCK_EMAIL_COUNT = 5;
    const BLOCK_PASSWORD_COUNT = 5;

    const BLOCK_DURATION = 60 * 15;

    const TOKEN_DEFUSE_ROUND = 3;
    const REFRESH_TOKEN_DEFUSE_ROUND = 3;

    /**
     * @throws ErrorException
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public static function login(App $object, object $input): object
    {
        if(!property_exists($input, 'email')){
            throw new ErrorException('E-mail is required.');
        }
        if(!property_exists($input, 'password')){
            throw new ErrorException('Password is required.');
        }
        //no server info available only in current
        $input->ip = (object)[
            'address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ];
        if(User::is_blocked($object, $input, $user, $logger) === false){
            //maybe add an outputfilter
            //password should become [redacted]
            $verify = password_verify($input->password, $user->password);
            if($verify === false){
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                $input->status = UserLogger::STATUS_INVALID_EMAIL_PASSWORD;
                //write is in the log
                $logger = UserLogger::log($object, $input);
                throw new ErrorException('Invalid e-mail-password.');
            } else {
                $input->status = UserLogger::STATUS_SUCCESS;
                $logger = UserLogger::log($object, $input);
            }
            //might need an outputfilter
            $user->password = '[redacted]';
            $user->ip = $input->ip ?? '0.0.0.0';
            $user->token = User::get_token($object, $user);
            $user->refresh_token = User::get_refresh_token($object, $user);
            return (object) [
                'node' => $user,
            ];
        }
        throw new Exception('User Blocked is blocked until: ' . $logger->is->blocked->until . ' with ip address: ' . $logger->ip->address);
    }

    /**
     * @throws FileWriteException
     * @throws ObjectException
     * @throws Exception
     */
    private static function get_token(App $object, object $user): string
    {
        $configuration = Jwt::configuration($object);
        $options = [];
        $options['user'] = $user;
        $token = Jwt::get($object, $configuration, $options);
        $string = $token->toString();
        $url = $object->config('project.dir.data') . 'Account/Jwt.json';
        $cache = $object->data(App::CACHE);
        $config = $cache->get(sha1($url));
        $crypt_url = $config->get('token.crypt_url') ?? null; //future
        if($crypt_url !== null){
            //if you want you can logout everyone from the system by changing the content of crypt_url
            $key = Core::key($crypt_url);
            for($i = 0; $i < User::TOKEN_DEFUSE_ROUND; $i++){
                $string = Crypto::encrypt($string, $key);
            }
            $string = gzencode($string, 9);
            $string = base64_encode($string);
             //around: 1800 chars fits in the 4KB cookie
//            $crypt_compressed = gzencode($crypt_string, 9); //around 1000 chars //json cant handle this data
            return $string;
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
     * @throws Exception
     */
    private static function get_refresh_token(App $object, object $user): string
    {
        $configuration = Jwt::configuration($object);
        $options = [];
        $options['user'] = $user;
        $options['refresh'] = true;
        $token = Jwt::refresh_get($object, $configuration, $options);
        $string = $token->toString();
        $url = $object->config('project.dir.data') . 'Account/Jwt.json';
        $cache = $object->data(App::CACHE);
        $config = $cache->get(sha1($url));
        $crypt_url = $config->get('token.crypt_url') ?? null;
        if($crypt_url) {
            //if you want you can logout everyone from the system by changing the content of crypt_url
            $key = Core::key($crypt_url);
            for ($i = 0; $i < User::REFRESH_TOKEN_DEFUSE_ROUND; $i++) {
                $string = Crypto::encrypt($string, $key);
            }
            $string = gzencode($string, 9);
            $string = base64_encode($string);
            //around: 1800 chars fits in the 4KB cookie
//            $crypt_compressed = gzencode($crypt_string, 9); //around 1000 chars //json cant handle this data
            return $string;
        } else {
            throw new Exception('property token.crypt_url not set in data/Account/Jwt.json on refresh.token.crypt_url not set in data/Account/Jwt.json.');;
        }
    }

    /**
     * @throws ErrorException
     * @throws ObjectException
     * @throws Exception
     */
    public static function is_blocked(App $object, object $options, null|object &$user=null, null|object &$logger=null): bool
    {
        if(!property_exists($options, 'email')){
            throw new ErrorException('Option email is required.');
        }
        $node = new Node($object);
        $class = 'Account.User';
        $record = $node->record(
            $class,
            $node->role_system(),
            [
                'where' => [
                    [
                        'attribute' => 'email',
                        'value' => $object->request('email'),
                        'operator' => '===',
                    ]
                ],
                'relation' => true
            ]
        );
        if(array_key_exists('REMOTE_ADDR', $_SERVER)){
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '0.0.0.0';
        }
        $input = (object) [
            'email' => $object->request('email'),
            'status' => UserLogger::STATUS_INVALID_EMAIL_PASSWORD,
            'ip' => $ip,
        ];
        if($record === null){
            $status = 401;
            Handler::header('Status: ' . $status, $status, true);
            $log = UserLogger::log($object, $input);
            return false;
        } else {
            //sorted by e-mail ip / status
            $count = UserLogger::count($object, $input);
            if($count >= User::BLOCK_PASSWORD_COUNT){
                $input->status = UserLogger::STATUS_BLOCKED;
                $log = UserLogger::log($object, $input);
                return true;
            }
            if(
                is_array($record) &&
                is_object($record['node'])
            ){
                $user = $record['node'];
            }
            return false;
        }
        /*
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
        */
        return false;
    }

    /**
     * @throws FileWriteException
     * @throws ObjectException
     * @throws ErrorException
     */
    /*
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
    */

    /**
     * @throws ObjectException
     * @throws AuthorizationException
     * @throws FileWriteException
     * @throws Exception
     */
    public static function current(App $object): array
    {
        $user = User::get_by_authorization($object);
        ddd($user);
        $node = User::expose($object, $user, __FUNCTION__);
        $data = [];
        $data['node'] = $node;
        return $data;
    }

    /**
     * @throws AuthorizationException
     * @throws ObjectException
     * @throws Exception
     */
    public static function expose(App $object, object $user, string $function): object
    {

        $role = $user->role ?? false;
        if($role === false){
            throw new Exception('Role ROLE_USER not found.');
        }
        ddd($user);


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
     * @throws AuthorizationException
     * @throws Exception
     */
    public static function get_by_key(App $object): null|object
    {
        $item = $object->config('user');
        if($item){
            return $item;
        } else {
            $key = $object->request('key');
            if(!$key){
                return null;
            }
            ddd($key);
        }
        if($item){
            if(property_exists($item, 'is')){
                if(!property_exists($item->is, 'active')){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Account is not active.');
                }
                elseif(
                    property_exists($item->is, 'deleted')
                    && !empty($item->is->deleted)
                ){
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Account is deleted.');
                }
                elseif(!property_exists($item, 'role') || empty($item->role)) {
                    $status = 401;
                    Handler::header('Status: ' . $status, $status, true);
                    throw new AuthorizationException('Account has no roles.');
                }
                $item->is->logged_in = microtime(true);
                $item->is->logged_in_date = new DateTime('@' . $item->is->logged_in);
                $object->config('user', $item);
                return $item;
            } else {
                throw new AuthorizationException('Account has no is->active.');
            }
        }
        return null;
    }

    /**
     * @throws AuthorizationException
     * @throws Exception
     */
    public static function get_by_uuid(App $object): null|object
    {
        $item = $object->config('user');
        if($item){
            return $item;
        } else {
            $uuid = $object->request('user.uuid');
            if(!$uuid){
                return null;
            }
            $class = 'Account.User';
            $node = new Node($object);
            $user = $node->record(
                $class,
                $node->role_system(),
                ['where' =>
                    [
                        [
                            'attribute' => 'uuid',
                            'value' => $uuid,
                            'operator' => '==='
                        ],
                        'and',
                        [
                            'attribute' => 'is.active',
                            'value' => 1,
                            'operator' => '>='
                        ]
                    ]
                ]
            );
            dd($user);
        }
        /*
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
        */
        return null;
    }

    /**
     * @throws ObjectException
     * @throws AuthorizationException
     * @throws FileWriteException
     * @throws UrlEmptyException
     */
    public static function get_by_authorization(App $object): null|object
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
        dd($token);
        if($crypt_url) {
            //if you want you can logout everyone from the system by changing the content of crypt_url
            $key = Core::key($crypt_url);
            $token = base64_decode($token); // around 5900 still doesn't fit in the 4KB cookie so its in localstorage which should be subdomain level specific and around 5 MB
            if(strlen($token) >= 1){
                try {
                    $token = gzdecode($token);
                    for($i = 0; $i < User::TOKEN_DEFUSE_ROUND; $i++){
                        $token = Crypto::decrypt($token, $key); //around: 7650 chars doesn't fit in the 4KB cookie
                    }
                }
                catch (Throwable $e) {
                    $input = (object) [
                        'ip' => (object)[
                            'address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                        ],
                        'token' => $token,
                        'status' => 'You don\'t have permission to access this resource. (Error: ' . $e->getMessage() . ' Line: ' . $e->getLine() . ' File:' . $e->getFile() . ')'
                    ];
                    $logger = TokenLogger::log($object, $input);
                    Core::redirect('/User/Login');
                    exit(0);
                }
            }
        }
        if(!$token){
            $input = (object) [
                'ip' => (object)[
                    'address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ],
                'token' => $token ?? null,
                'status' => 'You don\'t have permission to access this resource. (Error: ' . $e->getMessage() . ' Line: ' . $e->getLine() . ' File:' . $e->getFile() . ')'
            ];
            $logger = TokenLogger::log($object, $input);
            throw new AuthorizationException('Please provide a valid token...');
        }
        $token_unencrypted = Jwt::decryptToken($object, $token);
        $claims = $token_unencrypted->claims();
        if($claims->has('user')) {
            $user = $claims->get('user');
            dd($user);
            /*

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
             */
        }
        return null;
    }

}