<?php
namespace Package\Raxon\Account\Module;

use Exception;

use Raxon\App;
use Raxon\Exception\ErrorException;
use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;
class TokenLogger {

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws Exception
     */
    public static function log(App $object, object $input) : object
    {
        $time = microtime(true);
        if(
            property_exists($input, 'ip') &&
            property_exists($input->ip, 'address'))
        {
            //nothing
        } else {
            throw new ErrorException('Ip is required.');
        }
        if(!property_exists($input, 'token')){
            throw new ErrorException('Token is required.');
        }
        if(!property_exists($input, 'status')){
            throw new ErrorException('Status is required.');
        }
        $logger = (object) [
            'uuid' => Core::uuid(),
            'ip' => (object) [
                'address' => $input->ip->address ?? '0.0.0.0'
            ],
            'status' => $input->status,
            'is' => (object) [
                'created' => $time,
            ]
        ];
        TokenLogger::write($object, $logger);
        return $logger;
    }

    public static function write(App $object, object|null $logger=null): ?object
    {
        $dir = $object->config('project.dir.log');
        Dir::create($dir, Dir::CHMOD);
        $url = $object->config('project.dir.log') . 'token_logger' . $object->config('extension.jsonl');
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