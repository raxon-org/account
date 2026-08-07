<?php
namespace Package\Raxon\Account\Trait;

use DateTime;
use Entity\User;
use Exception;
use Raxon\App;
use Raxon\Config;
use Raxon\Doctrine\Module\Database;
use Raxon\Doctrine\Module\Entity;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\LocateException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\File;
use Raxon\Module\Data;
use Raxon\Node\Module\Node;

trait Admin {

    /**
     * @throws DirectoryCreateException
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function admin_create(object $flags, object $options): User
    {
        $object = $this->object();
        if(!property_exists($options, 'email')) {
            throw new Exception('Email is required');
        }
        if(!property_exists($options, 'password')) {
            throw new Exception('Password is required');
        }
        $object = $this->object();
        $node = new Node($object);
        $result = $node->record('Account.Role', $node->role_system(), [
            'filter' => [
                'name' => 'ROLE_ADMIN'
            ]
        ]);
        $class = 'Account.User';
        $record = $node->record(
            $class,
            $node->role_system(),
            [
                'where' => [
                    'email' => [
                        'value' => $options->email,
                        'operator' => '===',
                    ],
                    'role' =>  [
                        'value' => $result['node']->uuid,
                        'operator' => 'in.array',
                    ]
                ]
            ]
        );
        ddd($record);
        $time = time();
        $request = (object) [
            'email' => escapeshellarg($options->email),
            'password' => escapeshellarg($options->password),
            'role' => [
                $result['node']->uuid
            ],
            'isActive' => 0, //cannot activate immediately
            'isCreated' => new DateTime('@' . $time),
            'IsDeleted' => null,
            'isModified' => new DateTime('@' . $time)
        ];
        $response = $node->create($class, $node->role_system(), $request);
        ddd($response);
        /*
        $entity = 'User';
        $config = Database::config($object);
        $environments = $object->config('doctrine.environment'); 
        $framework_environment = $object->config('framework.enviroment');       
        foreach($environments as $name => $list){
            if($name === $options->connection){
                foreach($list as $environment => $connection){
                    if($environment === $framework_environment){
                        break 2;
                    }
                    elseif($environment === '*'){
                        break 2;
                    }
                }
            }
        }        
        $connection->manager = Database::entity_manager($object, $config, $connection);
        $validate_url = Entity::get_validate_url($object, $entity);        
        $validation = Entity::get_validation($object, $validate_url, $entity . '.patch');
        $object->config('doctrine.entity.manager', $connection->manager);
        $validate = false;
        $user = null;
        $error = null;
        if(File::exist($validate_url)) {
            $data_node = new Data($request);
            $validate = Entity::validate($object, $validation, $data_node->data());
        }
        if(
            is_object($validate) &&
            property_exists($validate, 'success') &&
            $validate->success === true
        ){
            $request->password = password_hash($options->password, PASSWORD_BCRYPT, [
                'cost' => 13
            ]);
            $user = Entity::create($object, $connection, $node->role_system(), $entity, $request, $error);
        }
        if(
            !is_object($user) ||
            (
                is_object($user) &&
                $user->getId() === null
            )
        ){
            echo Core::object($validate, Core::JSON) . PHP_EOL;
            throw new Exception('User not created');
        }
        elseif($user === null && $error !== null){
            return $error;
        }
        echo 'User ('. $options->email .') created' . PHP_EOL;
        $object->request('entity', $entity);
        $request = (object) [
            'id' => $user->getId(),
            'isActive' => 1,                       
        ];
        $user = Entity::patch($object, $connection, $node->role_system(), $request, $error);
        return $user;
    }

    /**
     * @throws ObjectException
     * @throws LocateException
     * @throws FileWriteException
     * @throws Exception
     */
    public function admin_password_change(object $flags, object $options): User
    {
        $object = $this->object();
        if(!property_exists($options, 'email')) {
            throw new Exception('Email is required');
        }
        if(!property_exists($options, 'password')) {
            throw new Exception('Password is required');
        }
        if(!property_exists($options, 'connection')) {
            $options->connection = 'system';
            $options->environment = '*';
        }
        if(!property_exists($options, 'environment')) {
            throw new Exception('Environment is required');
        }
        $connection = Database::connection($object, $flags, $options);
        $node = new Node($object);
        $entity = 'User';
        $validate_url = Entity::get_validate_url($object, $entity);
        $validation = Entity::get_validation($object, $validate_url, $entity . '.patch');
        $object->config('doctrine.entity.manager', $connection->manager);
        $options_entity = [
            'filter' => [
                'email' => $options->email
            ]
        ];
        $object->request('entity', $entity);
        $response = Entity::record($object, $connection, $node->role_system(), $options_entity);
        dd($response);


//        public static function patch(App $object, object $connection, object $role, object $request, object|null &$error=null): ?object


//        ddd($options_entity);
//        public static function record(App $object, EntityManager $entityManager, $role, $options=[]): array



        $response = Entity::record($object, $coonnection, $node->role_system(), $entity, $options_entity);





        $object->request('entity', $entity);
        $request = (object) [
            'id' => $user->getId(),
            'isActive' => 1,
        ];
        $user = Entity::patch($object, $connection, $node->role_system(), $request, $error);
        $request = (object) [
            'email' => $options->email,
            'password' => $options->password,
            'role' => [
                $result['node']->uuid
            ],
            'isActive' => 0, //cannot activate immediately
            'isCreated' => new DateTime('@' . $time),
        ];
        $entity = 'User';
        $config = Database::config($object);
        $environments = $object->config('doctrine.environment');
        $framework_environment = $object->config('framework.enviroment');
        foreach($environments as $name => $list){
            if($name === $options->connection){
                foreach($list as $environment => $connection){
                    if($environment === $framework_environment){
                        break 2;
                    }
                    elseif($environment === '*'){
                        break 2;
                    }
                }
            }
        }
        $connection->manager = Database::entity_manager($object, $config, $connection);
        $validate_url = Entity::get_validate_url($object, $entity);
        $validation = Entity::get_validation($object, $validate_url, $entity . '.patch');
        $object->config('doctrine.entity.manager', $connection->manager);
        $validate = false;
        $user = null;
        $error = null;
        if(File::exist($validate_url)) {
            $data_node = new Data($request);
            $validate = Entity::validate($object, $validation, $data_node->data());
        }
        if(
            is_object($validate) &&
            property_exists($validate, 'success') &&
            $validate->success === true
        ){
            $request->password = password_hash($options->password, PASSWORD_BCRYPT, [
                'cost' => 13
            ]);
            $user = Entity::create($object, $connection, $node->role_system(), $entity, $request, $error);
        }
        if(
            !is_object($user) ||
            (
                is_object($user) &&
                $user->getId() === null
            )
        ){
            echo Core::object($validate, Core::JSON) . PHP_EOL;
            throw new Exception('User not created');
        }
        elseif($user === null && $error !== null){
            return $error;
        }
        echo 'User ('. $options->email .') created' . PHP_EOL;
        $object->request('entity', $entity);
        $request = (object) [
            'id' => $user->getId(),
            'isActive' => 1,
        ];
        $user = Entity::patch($object, $connection, $node->role_system(), $request, $error);
        return $user;
    }

     
}