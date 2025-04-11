<?php
namespace Package\Raxon\Account\Module;

use DateTime;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;

use Entity\User as Entity;

use Exception;

use Raxon\App;

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
    public static function login(App $object, object $input): array
    {
        if(!property_exists($input, 'email')){
            throw new ErrorException('E-mail is required.');
        }
        if(!property_exists($input, 'password')){
            throw new ErrorException('Password is required.');
        }
        $config = Database::config($object);
        $connection = $object->config('doctrine.environment.system.*');
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
                $user = User::expose($object, $node);
                $user['token'] = User::get_token($object, $node);
                $user['refreshToken'] = User::get_refresh_token($object, $node);
                $encrypted_refreshToken = sha1($user['refreshToken']);
                $repository = $connection->manager->getRepository(Entity::class);
                $cost = 13;
                $node->setRefreshToken(password_hash($encrypted_refreshToken, PASSWORD_BCRYPT, [
                    'cost' => $cost
                ]));
                $node->setIsLoggedIn(new DateTime());
                $connection->manager->persist($node);
                $connection->manager->flush();
                $data = [];
                $data['node'] = $user;
                return $data;
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
            throw new ErrorException('User blocked.');
        }
    }

    /**
     * @throws AuthorizationException
     * @throws ObjectException
     * @throws Exception
     */
    private static function expose(App $object, Entity $record): array
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
        $role = $response['list'][0];
        ddd($role);
        $entity = 'User';
        $function = 'login';
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
        return $record;
    }

    /**
     * @throws FileWriteException
     * @throws ObjectException
     */
    private static function get_token(App $object, Entity $node): string
    {
        $configuration = Jwt::configuration($object);
        $options = [];
        $options['user'] = $node;
        $token = Jwt::get($object, $configuration, $options);
        return $token->toString();
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

        d($token);
        d($refreshToken);


        $record->token = $token;
        $record->refresh_token = $refreshToken;

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
        return $record;
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
    public static function is_blocked(App $object, object $input, object $connection=null): bool
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

}