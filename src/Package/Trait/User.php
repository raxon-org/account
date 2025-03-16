<?php
namespace Package\Raxon\Account\Trait;

use Raxon\Doctrine\Module\Database;
use Raxon\Doctrine\Module\Entity;

use Exception;

trait User
{

    /**
    * @throws Exception
    */
    public function user_read($flags, $options)
    {
        $object = $this->object();
        if (!property_exists($options, 'connection')) {
            throw new Exception('Option connection required.');
        }
        if (!property_exists($options, 'environment')) {
            $options->environment = $object->config('framework.environment');
        }
        if (
            property_exists($options, 'email') ||
            property_exists($options, 'uuid') ||
            property_exists($options, 'id')
        ) {
            //nothing
        } else {
            throw new Exception('Option email, id or uuid required.');
        }
        $config = Database::config($object);
        $connection = $object->config('doctrine.environment.' . $options->connection . '.' . $options->environment);
        if($connection === null){
            $connection = $object->config('doctrine.environment.' . $options->connection . '.' . '*');
        }
        $em = Database::entity_manager($object, $config, $connection);

        $user = Entity::readById($object, $em, 'Entity\User', 1);
        d($user);
        ddd($options);
    }
}