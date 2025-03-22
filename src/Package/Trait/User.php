<?php
namespace Package\Raxon\Account\Trait;

use DateTime;

use Doctrine\ORM\Exception\ORMException;

use Raxon\Module\Cli;

use Raxon\Doctrine\Module\Database;
use Raxon\Doctrine\Module\Entity;

use Raxon\Node\Module\Node;

use Exception;

use Raxon\Exception\ObjectException;

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
     * @throws ORMException
     */
    public function setup_admin($flags, $options): mixed
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
        $request = (object) [
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
        $input =  (int) Cli::read('input', 'Enter connection number: ') - 1;
        $connection = $list_connection[$input] ?? null;
        $connection->manager = Database::entity_manager($object, $config, $connection);
        $user = Entity::create($object, $connection, $node->role_system(), $entity, $request, $error);
        if(is_object($user) && $user->getId() === null){
            throw new Exception('User not created');
        }
        elseif($user === null && $error !== null){
            return $error;
        }
        echo 'User ('. $email .') created' . PHP_EOL;
        $request = (object) [
            'id' => $user->getId(),
            'isActive' => 1
        ];
        $user = Entity::patch($object, $connection, $node->role_system(), $entity, $request, $error);
        return null;
    }

    public function setup_anonymous($flags, $options)
    {
        $object = $this->object();
        $url = $object->config('project.dir.vendor') . 'raxon/account/Data/Role.Anonymous.json';
        $data = $object->data_read($url);
        $permission_array = [];
        if($data){
            foreach($data->get('permission') as $permission){
                $node = new Node($object);
                $response = $node->record('Account.Permission', $node->role_system(), [
                    'filter' => [
                        'name' => $permission->name
                    ]
                ]);
                if(
                    array_key_exists('node', $response) &&
                    property_exists($response['node'], 'uuid')
                ){
                    $permission_array[] = $response['node']->uuid;
                }
                else{
                    //create permission
                    $response = $node->create(
                        'Account.Permission',
                        $node->role_system(),
                        [
                            'name' => $permission->name
                        ]
                    );
                    ddd($response);
                }
            }
        }
        $response = $node->record('Account.Role', $node->role_system(), [
            'filter' => [
                'name' => $data->get('name')
            ]
        ]);
        if(
            array_key_exists('node', $response) &&
            property_exists($response['node'], 'uuid')
        ){
            $role = $response['node'];
            $role->rank = $data->get('rank');
            $role->permission = $permission_array;
            $response = $node->put(
                'Account.Role',
                $node->role_system(),
                [
                    'uuid' => $role->uuid,
                    'name' => $data->get('name'),
                    'rank' => $data->get('rank'),
                    'permission' => $permission_array
                ]
            );
            $role = $response['node'] ?? (object) [];
        }
        else{
            $response = $node->create(
                'Account.Role',
                $node->role_system(),
                [
                    'name' => $data->get('name'),
                    'rank' => $data->get('rank'),
                    'permission' => $permission_array
                ]
            );
            $role = $response['node'] ?? (object) [];
        }
        if(property_exists($role, 'uuid')){
            return 'ROLE_ANONYMOUS created / reset...';
        }
    }
}