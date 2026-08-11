<?php
namespace Package\Raxon\Account\Module;

use DateTime;

use Exception;

use Raxon\App;
use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Raxon\Exception\ErrorException;

class UserLogger
{
    const STATUS_BLOCKED = 'blocked';
    const STATUS_SUCCESS = 'success';
    const STATUS_INVALID_EMAIL_PASSWORD = 'invalid-email-password';

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function log(App $object, object $input) : object
    {
        $time = time();
        if(!property_exists($input, 'status')){
            throw new ErrorException('Status is required.');
        }
        if(!property_exists($input, 'email')){
            throw new ErrorException('Email is required.');
        }
        $logger = (object) [];
        if(array_key_exists('REMOTE_ADDR', $_SERVER)){
            $logger->ipAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $logger->ipAddress = '0.0.0.0';
        }
        $logger->is = (object) [
            'created' => new DateTime('@' . $time)
        ];
        $dir = $object->config('project.dir.log');
        Dir::create($dir, Dir::CHMOD);
        $url = $object->config('project.dir.log') . 'user_logger' . $object->config('extension.jsonl');
        File::append($url, Core::object($logger, Core::JSON_LINE) . "\n");
        File::permission($object, [
            'url' => $url
        ]);
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
        $time = $object->config('server.default.user.block.period') ?? '15 minutes';
        $time = '- ' . $time;
        return 0;
        /*
        if(
            $user !== null &&
            get_class($user) === '\Entity\User'
        ){
            $user_id = $user->getId();
            $dateTime = date('Y-m-d H:i:s', strtotime($time));
            $result = $connection->manager->createQuery(UserLogger::QUERY_FIND_LOG)
                ->setParameter('userId', $user_id)
                ->setParameter('status', $status)
                ->setParameter('dateTime', $dateTime)
                ->getResult();
            return count($result);
        } else {
            if(array_key_exists('REMOTE_ADDR', $_SERVER)){
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $dateTime = date('Y-m-d H:i:s', strtotime($time));
                $result = $connection->manager->createQuery(UserLogger::QUERY_FIND_LOG_IP)
                    ->setParameter('ipAddress', $ipAddress)
                    ->setParameter('status', $status)
                    ->setParameter('dateTime', $dateTime)
                    ->getResult();
                return count($result);
            } else {
                $ipAddress = '0.0.0.0';
                $dateTime = date('Y-m-d H:i:s', strtotime($time));
                $result = $connection->manager->createQuery(UserLogger::QUERY_FIND_LOG_IP)
                    ->setParameter('ipAddress', $ipAddress)
                    ->setParameter('status', $status)
                    ->setParameter('dateTime', $dateTime)
                    ->getResult();
                return count($result);
            }
        }
        */
    }

}