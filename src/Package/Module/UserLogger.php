<?php
namespace Package\Raxon\Account\Module;

use DateTime;

use Exception;

use Entity\User;
use Entity\UserLogger as Entity;

use Raxon\App;

use Raxon\Doctrine\Module\Database;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Exception\ORMException;

use Raxon\Exception\ErrorException;

class UserLogger
{
    const STATUS_BLOCKED = 'blocked';
    const STATUS_SUCCESS = 'success';
    const STATUS_INVALID_PASSWORD = 'invalid-password';
    const STATUS_INVALID_EMAIL = 'invalid-email';
    const STATUS_INVALID_EMAIL_PASSWORD = 'invalid-email-password';

    const LOGIN_PERIOD = '-15 Minutes';

    const QUERY_FIND_LOG = '
        SELECT l
        FROM ' . Entity::class .' l 
        WHERE l.userId LIKE :userId  
        AND l.status = :status 
        AND l.dateTime >= :dateTime 
        ';

    const QUERY_FIND_LOG_IP= '
        SELECT l 
        FROM ' . Entity::class . ' l 
        WHERE l.userId IS NULL 
        AND l.status = :status 
        AND l.ipAddress = :ipAddress  
        AND l.dateTime >= :dateTime        
        ';

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function log(App $object, object $input, User|null $user=null, object|null $connection=null,): Entity
    {
        if(!property_exists($input, 'status')){
            throw new ErrorException('Status is required.');
        }
        if($connection === null){
            $config = Database::config($object);
            $connection = $object->config('doctrine.environment.system.*');
            $connection->manager = Database::entity_manager($object, $config, $connection);
        }
        $options = [];
        $logger = new Entity();
        if(array_key_exists('REMOTE_ADDR', $_SERVER)){
            $logger->setIpAddress($_SERVER['REMOTE_ADDR']);
        } else {
            $logger->setIpAddress('0.0.0.0');
        }
        $logger->setDateTime(new dateTime());
        if(
            $user !== null &&
            get_class($user) === 'Entity\User'
        ){
            $logger->setUserid($user->getId());
        }
        $logger->setStatus($input->status);
//        $connection->manager->persist($logger);
//        $connection->manager->flush();
        return $logger;
    }

    /**
     * @throws ErrorException
     * @throws Exception
     */
    public static function count(App $object, object $input, User|null $user=null, object|null $connection=null): int
    {
        if($connection === null){
            $config = Database::config($object);
            $connection = $object->config('doctrine.environment.system.*');
            $connection->manager = Database::entity_manager($object, $config, $connection);
        }
        if(!property_exists($input, 'status')){
            throw new ErrorException('Status is required.');
        }
        $status = $input->status;
        if(
            $user !== null &&
            get_class($user) === '\Entity\User'
        ){
            $user_id = $user->getId();
            $dateTime = date('Y-m-d H:i:s', strtotime(UserLogger::LOGIN_PERIOD));
            $result = $connection->manager->createQuery(UserLogger::QUERY_FIND_LOG)
                ->setParameter('userId', $user_id)
                ->setParameter('status', $status)
                ->setParameter('dateTime', $dateTime)
                ->getResult();
            return count($result);
        } else {
            if(array_key_exists('REMOTE_ADDR', $_SERVER)){
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $dateTime = date('Y-m-d H:i:s', strtotime(UserLogger::LOGIN_PERIOD));
                $result = $connection->manager->createQuery(UserLogger::QUERY_FIND_LOG_IP)
                    ->setParameter('ipAddress', $ipAddress)
                    ->setParameter('status', $status)
                    ->setParameter('dateTime', $dateTime)
                    ->getResult();
                return count($result);
            } else {
                $ipAddress = '0.0.0.0';
                $dateTime = date('Y-m-d H:i:s', strtotime(UserLogger::LOGIN_PERIOD));
                $result = $connection->manager->createQuery(UserLogger::QUERY_FIND_LOG_IP)
                    ->setParameter('ipAddress', $ipAddress)
                    ->setParameter('status', $status)
                    ->setParameter('dateTime', $dateTime)
                    ->getResult();
                return count($result);
            }
        }
    }
}