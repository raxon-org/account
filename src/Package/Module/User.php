<?php
namespace Package\Raxon\Account\Module;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Entity\User as Entity;

//use Entity\User as Entity;


use Raxon\App;

use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Handler;
use Raxon\Module\Response;

use Raxon\Doctrine\Module\Database;
use Raxon\Node\Module\Node;

use Exception;

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
     */
    public static function login(App $object): array
    {
        $config = Database::config($object);
        $connection = $object->config('doctrine.environment.system.*');
        $connection->manager = Database::entity_manager($object, $config, $connection);
        if(User::is_blocked($object, $connection, $object->request('email')) === false){
            $repository = $connection->manager->getRepository(Entity::class);
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
                    Userlogger::log($object, $connection, $node, UserLogger::STATUS_INVALID_EMAIL_PASSWORD);
                    throw new ErrorException('Invalid e-mail-password.');
                }
                Userlogger::log($object, $connection, $node, UserLogger::STATUS_SUCCESS);
                $array = User::getTokens($object, $connection, $node);
                $data = [];
                $data['node'] = $array;
                return $data;
            } else {
                $status = 401;
                Handler::header('Status: ' . $status, $status, true);
                Userlogger::log($object, $connection, null, UserLogger::STATUS_INVALID_EMAIL_PASSWORD);
                throw new ErrorException('Invalid e-mail-password.');
            }
        } else {
            $status = 401;
            Handler::header('Status: ' . $status, $status, true);
            Userlogger::log($object, $connection, null, UserLogger::STATUS_BLOCKED);
            throw new ErrorException('User blocked.');
        }
    }

    /**
     * @throws Exception
     */
    private static function getTokens(App $object, $record): mixed
    {
        $configuration = Jwt::configuration($object);
        $options = [];
        $options['user'] = $record;
        $token = Jwt::get($object, $configuration, $options);
        $token = $token->toString();
        $options['refresh'] = true;
        $configuration = Jwt::configuration($object, $options);
        $refreshToken = Jwt::refresh_get($object, $configuration, $options);
        $refreshToken = $refreshToken->toString();
        $encrypted_refreshToken = sha1($refreshToken);

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
    public static function is_blocked(App $object, object $connection=null, $email=''): bool
    {
        if($connection === null){
            $config = Database::config($object);
            $connection = $object->config('doctrine.environment.system.*');
            $connection->manager = Database::entity_manager($object, $config, $connection);
        }
        $repository = $connection->manager->getRepository(Entity::class);
        $node = $repository->findOneBy(['email' => $email]);
        if($node){
            $count = UserLogger::count($object, $connection, $node, UserLogger::STATUS_INVALID_EMAIL_PASSWORD);
            if($count >= User::BLOCK_PASSWORD_COUNT){
                Userlogger::log($object, $connection, $node, UserLogger::STATUS_BLOCKED);
                return true;
            }
        } else {
            $count = UserLogger::count($object, $connection, null, UserLogger::STATUS_INVALID_EMAIL_PASSWORD);
            if($count >= User::BLOCK_EMAIL_COUNT){
                Userlogger::log($object, $connection, $node, UserLogger::STATUS_BLOCKED);
                return true;
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