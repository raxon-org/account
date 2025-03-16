<?php
namespace Package\Raxon\Account\Trait;

use DateTime;

use Doctrine\ORM\Exception\ORMException;

use Raxon\Module\Cli;

use Raxon\Doctrine\Module\Database;
use Raxon\Doctrine\Module\Entity;

use Raxon\Node\Module\Node;

use Exception;


trait User
{

    /**
    * @throws Exception
     * @throws ORMException
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
        $node = new Node($object);
        $user = Entity::readById($object, $em, $node->role_system(), 'User', 1);
        d($user);
        ddd($options);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function setup_admin($flags, $options): array
    {
        echo 'Create admin account' . PHP_EOL;
        echo 'Press ctrl-c to abort' . PHP_EOL;
        $email = Cli::read('input', 'Email: ');
        $password = Cli::read('input-hidden', 'Password: ');
        $password_repeat = Cli::read('input-hidden', 'Password repeat: ');
        while(true){
            if($password === $password_repeat){
                break;
            }
            echo 'Passwords do not match' . PHP_EOL;
            $password = Cli::read('input-hidden', 'Password: ');
            $password_repeat = Cli::read('input-hidden', 'Password repeat: ');
        }
        $object = $this->object();
        $node = new Node($object);
        $result = $node->record('Account.Role', $node->role_system(), [
            'filter' => [
                'name' => 'ROLE_ADMIN'
            ]
        ]);
        $time = time();
        $request = [
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT, [
                'cost' => 13
            ]),
            'role' => [
                $result['node']->uuid
            ],
            'isActive' => 0, //cannot activate immediately
            'isCreated' => new DateTime('@' . $time),
        ];
        $entity = 'User';
        $config = Database::config($object);

        $environments = $object->config('doctrine.environment');
        $nr = 0;
        $list_connection = [];
        foreach($environments as $name => $list){
            foreach($list as $environment => $connection){
                $list_connection[$nr] = $connection;
                echo '(' .  $nr + 1 . ') ' . $name . ' ' . $environment . PHP_EOL;
                $nr++;

            }
        }
        $input =  (int) Cli::read('input', 'Enter connection number') - 1;
        $connection = $list_connection[$input] ?? null;
        d($input);
        d($connection);
        ddd($environments);
        $em = Database::entity_manager($object, $config, $connection);
        $user = Entity::create($object, $em, $node->role_system(), $entity, $request);




        ddd($user);


        Database::instance($object, Database::SYSTEM);
        $entityManager = Database::entityManager($object, ['name' => Database::SYSTEM]);
        $options_entity = [
            'filter' => [
                'email' => $email
            ]
        ];
        $response = Entity::record($object, $entityManager, $node->role_system(), $entity, $options_entity);
        if(
            array_key_exists('node', $response) &&
            is_object($response['node']) &&
            property_exists($response['node'], 'uuid')
        ){
            ddd($object->request());
            Entity::updateByUuid($object, $entity, $response['node']->uuid);
        } else {
            $object->request('node', $user);
            $create = Entity::create($object, $entityManager, $node->role_system(), $entity, $object->request('node'));
            ddd($create);
            //we can create a record and save it to the database
        }
        /*
        ddd($response);
        $result = $node->create('Account.User', $node->role_system(), $user, $options);
        if(
            array_key_exists('node', $result) &&
            property_exists($result['node'], 'uuid')
        ){

            $result = $node->patch('Account.User', $node->role_system(), [
                'uuid' => $result['node']->uuid,
                'is' => (object) [
                    'active' => $time
                ]
            ], $options);
        }
        */
        return $result;
    }
}