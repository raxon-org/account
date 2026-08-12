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
        $time = microtime(true);
        if(!property_exists($input, 'status')){
            throw new ErrorException('Status is required.');
        }
        if(!property_exists($input, 'email')){
            throw new ErrorException('Email is required.');
        }
        $logger = (object) [
            'uuid' => Core::uuid(),
        ];
        $logger->status = $input->status;
        if($logger->status === self::STATUS_BLOCKED){
            $logger->is = (object) [
                'created' => $time,
                'blocked' => (object) [
                    'since' => $time,
                    'until' => $time + User::BLOCK_DURATION,
                ]
            ];
        } else {
            $logger->is = (object) [
                'created' => $time,
            ];
        }
        UserLogger::write($object, $logger);
        return $logger;
    }

    public static function write(App $object, object|null $logger=null): ?object
    {
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
    public static function count(App $object, object $input, ): int
    {
        //need to implement this:
        //read data and then in_array make binary trees and sort on e-mail / ip address and count
        return 0;
    }

}