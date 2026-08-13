<?php
namespace Package\Raxon\Account\Trait;

use DateTime;
use Exception;
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
    public function admin_create(object $flags, object $options): object
    {
        $object = $this->object();
        if(!property_exists($options, 'email')) {
            throw new Exception('-option email is required');
        }
        if(!property_exists($options, 'password')) {
            throw new Exception('-option password is required');
        }
        $node = new Node($object);
        $class = 'Account.Role';
        $result = $node->record($class,
            $node->role_system(),
            [
                'filter' => [
                    'name' => 'ROLE_ADMIN'
                ]
            ]
        );
        $class = 'Account.User';
        $record = $node->record(
            $class,
            $node->role_system(),
            [
                'where' => [
                    [
                        'attribute' => 'email',
                        'value' => $options->email,
                        'operator' => '===',
                    ],
                    [
                        'attribute' => 'role',
                        'value' => $result['node']->uuid,
                        'operator' => 'in.array',
                    ]
                ]
            ]
        );
        if($record === null) {
            $time = microtime(true);
            $request = (object) [
                'email' => $options->email,
                'password' => $options->password,
                'role' => [
                    $result['node']->uuid
                ],
                'is' => (object) [
                    'active' => 0, //cannot activate immediately
                    'loggedIn' => null,
                    'created' => $time,
                    'updated' => $time,
                    'deleted' => null
                ]
            ];
            $response = $node->create($class, $node->role_system(), $request);
            if(
                is_array($response) &&
                array_key_exists('error', $response)
            ){
                echo Core::object($response, Core::JSON) . PHP_EOL;
                throw new Exception('User not created');
            }
            else if(
                is_array($response) &&
                array_key_exists('node', $response) &&
                property_exists($response['node'], 'uuid')
            ) {
                dd($response['node']);
                $patch = (object) [
                    'uuid' => $response['node']->uuid,
                    'is' => (object) [
                        'active' => 1,
                    ],
                    'password' => password_hash($response['node']->password, PASSWORD_BCRYPT,
                        [
                            'cost' => 13
                        ]
                    )
                ];
                $response = $node->patch($class, $node->role_system(), $patch);
            }
        } else {
            if(array_key_exists('node', $record)){
                return $record['node'];
            }
        }
        throw new Exception('User not created');
    }

    /**
 * @throws FileWriteException
 * @throws ObjectException
 * @throws Exception
 */
    public function admin_email_change(object $flags, object $options): object
    {
        if(!property_exists($options, 'email')) {
            throw new Exception('-email is required');
        }
        if(!property_exists($options, 'password')) {
            throw new Exception('-option password is required');
        }
        $time = time();
        $object = $this->object();
        $node = new Node($object);
        $class = 'Account.Role';
        $result = $node->record($class,
            $node->role_system(),
            [
                'filter' => [
                    'name' => 'ROLE_ADMIN'
                ]
            ]
        );
        $class = 'Account.User';
        $record = $node->record(
            $class,
            $node->role_system(),
            [
                'where' => [
                    [
                        'attribute' => 'email',
                        'value' => $options->email,
                        'operator' => '===',
                    ],
                    [
                        'attribute' => 'role',
                        'value' => $result['node']->uuid,
                        'operator' => 'in.array',
                    ]
                ]
            ]
        );
        $patch = (object) [
            'uuid' =>$record['node']->uuid,
            'email' => $options->email,
            'is' => (object) [
                'updated' => new DateTime('@' . $time),
            ]
        ];
        $response = $node->patch($class, $node->role_system(), $patch);
        if(array_key_exists('node', $response)){
            return $response['node'];
        }
        throw new Exception('User e-mail not changed: '. $options->email);
    }

    /**
     * @throws FileWriteException
     * @throws ObjectException
     * @throws Exception
     */
    public function admin_password_change(object $flags, object $options): object
    {
        if(!property_exists($options, 'email')) {
            throw new Exception('-email is required');
        }
        if(!property_exists($options, 'password')) {
            throw new Exception('-option password is required');
        }
        $time = time();
        $object = $this->object();
        $node = new Node($object);
        $class = 'Account.Role';
        $result = $node->record($class,
            $node->role_system(),
            [
                'filter' => [
                    'name' => 'ROLE_ADMIN'
                ]
            ]
        );
        $class = 'Account.User';
        $record = $node->record(
            $class,
            $node->role_system(),
            [
                'where' => [
                    [
                        'attribute' => 'email',
                        'value' => $options->email,
                        'operator' => '===',
                    ],
                    [
                        'attribute' => 'role',
                        'value' => $result['node']->uuid,
                        'operator' => 'in.array',
                    ]
                ]
            ]
        );
        $password = password_hash(
            $options->password, PASSWORD_BCRYPT,
            [
                'cost' => 13
            ]
        );

        $patch = (object) [
            'uuid' =>$record['node']->uuid,
            'password' => $password,
            'is' => (object) [
                'updated' => new DateTime('@' . $time),
            ]
        ];
        $response = $node->patch($class, $node->role_system(), $patch);
        if(
            array_key_exists('node', $response) &&
            is_object($response['node'])
        ){
            return $response['node'];
        }
        throw new Exception('User password not changed: '. $options->email);
    }
     
}