<?php
namespace Package\Raxon\Org\Account\Trait;

use Composer\ClassMapGenerator\PhpFileParser;
use Raxon\Org\App;
use Raxon\Org\Config;

use Raxon\Org\Module\Cli;
use Raxon\Org\Module\Core;
use Raxon\Org\Module\File;
use Raxon\Org\Module\Dir;
use Raxon\Org\Module\Handler;

use Raxon\Org\Node\Model\Node;

use Package\Raxon\Org\Account\Service\User as Service;

use Exception;

use Raxon\Org\Exception\FileWriteException;
use Raxon\Org\Exception\ObjectException;

trait Main
{

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function setup_role($flags, $options)
    {
        Core::interactive();
        $object = $this->object();
        $url_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            'Account.Role' .
            $object->config('extension.json')
        ;
        $url_default = $object->config('project.dir.package') .
            'Raxon' .
            $object->config('ds') .
            'Org' .
            $object->config('ds') .
            'Account' .
            $object->config('ds') .
            'Data' .
            $object->config('ds') .
            'Account' .
            $object->config('ds') .
            'Account.Role' .
            $object->config('extension.json')
        ;
        if(File::exist($url_data)){
            if(property_exists($options, 'force')){
                //nothing
            }
            elseif(property_exists($options, 'patch')){
                //nothing
            }
            else {
                return false;
            }
        }
        if(property_exists($options, 'patch')){
            $data = $object->data_read($url_data);
            $node = new Node($object);

            ddd($data);
        } else {
            if(File::exist($url_data)){
                File::delete($url_data);
            }
            $data_default = $object->data_read($url_default);
            if($data_default){
                $node = new Node($object);
                $result = $node->create_many(
                    'Account.Role',
                    $node->role_system(),
                    $data_default->data('Account.Role'),
                    $options
                );
                return $result;
            }
        }
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function setup_permission($flags, $options): void
    {
        Core::interactive();
        $object = $this->object();
        $url_role_system = $object->config('project.dir.data') .
            'Account' .
            $object->config('ds') .
            'Role.System' .
            $object->config('extension.json')
        ;
        $url_data = $object->config('project.dir.node') .
            'Data' .
            $object->config('ds') .
            'Account.Permission' .
            $object->config('extension.json')
        ;
        if(File::exist($url_data)){
            if(property_exists($options, 'force')){
                //nothing
            }
            elseif(property_exists($options, 'patch')){
                //nothing
            }
            else {
                return;
            }
        }
        if(
            (
                property_exists($options, 'patch') &&
                $options->patch === true
            )
            ||
            (
                property_exists($options, 'force') &&
                $options->force === true
            )
        ){
            $data = $object->data_read($url_data);
            if($data){
                $data_role_system = $object->data_read($url_role_system);
                foreach($data->get('Account.Permission') as $nr => $record){
                    $is_found = false;
                    foreach($data_role_system->get('permission') as $permission){
                        if($record->name === $permission->name){
                            $is_found = true;
                            break;
                        }
                    }
                    if($is_found === false){
                        //delete record
                        $node = new Node($object);
                        $result = $node->delete(
                            'Account.Permission',
                            $node->role_system(),
                            [
                                'uuid' => $record->uuid
                            ],
                            $options
                        );
                        echo 'delete: ' . $record->name . PHP_EOL;
                    }
                }
                foreach($data_role_system->get('permission') as $permission){
                    $is_found = false;
                    foreach($data->get('Account.Permission') as $nr => $record){
                        if($record->name === $permission->name){
                            $is_found = true;
                            break;
                        }
                    }
                    if($is_found === false){
                        //insert record
                        $node = new Node($object);
                        $result = $node->create(
                            'Account.Permission',
                            $node->role_system(),
                            $permission,
                            $options
                        );
                        echo 'insert: ' . $permission->name . PHP_EOL;
                    }
                }
            }
        } else {
            if(File::exist($url_data)){
                echo 'delete: ' . $url_data . PHP_EOL;
                File::delete($url_data);
            }
            $data_role_system = $object->data_read($url_role_system);
            if($data_role_system){
                $node = new Node($object);
                $result = $node->create_many(
                    'Account.Permission',
                    $node->role_system(),
                    $data_role_system->data('permission'),
                    $options
                );
                if(array_key_exists('count', $result)){
                    echo 'inserted: ' . $result['count'] . ' items' . PHP_EOL;
                }
            }
        }
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function setup_jwt($flags, $options)
    {
        $object = $this->object();
        $url_jwt = $object->config('project.dir.data') . 'Account/Jwt.json';
        if (File::exist($url_jwt)) {
            if (property_exists($options, 'force')) {
                File::delete($url_jwt);
            } else {
                return false;
            }
        }
        if (!property_exists($options, 'token')) {
            $options->token = (object)[];
        }
        $permitted_for = Core::uuid();
        if (!property_exists($options->token, 'private_key')) {
            $options->token->private_key = '{{config(\'project.dir.data\')}}Ssl/Token_key.pem';
            if (!File::exist($object->config('project.dir.data') . 'Ssl/Token_key.pem')) {
                //create private key
                //create certificate
                $command = Core::binary($object) .
                    ' raxon_org/basic' .
                    ' openssl' .
                    ' init' .
                    ' -keyout=' . 'Token_key.pem' .
                    ' -out=' . 'Token_cert.pem';
                exec($command, $output, $code);
                if ($code !== 0) {
                    throw new Exception('Error creating private key & certificate' . implode(PHP_EOL, $output) . PHP_EOL);
                }
            }
        }
        if (!property_exists($options->token, 'certificate')) {
            $options->token->certificate = '{{config(\'project.dir.data\')}}Ssl/Token_cert.pem';
        }
        if (!property_exists($options->token, 'passphrase')) {
            $options->token->passphrase = '';
        }
        if (!property_exists($options->token, 'issued_at')) {
            $options->token->issued_at = 'now';
        }
        if (!property_exists($options->token, 'identified_by')) {
            $options->token->identified_by = Core::uuid();
        }
        if (!property_exists($options->token, 'permitted_for')) {
            $options->token->permitted_for = $permitted_for;
        }
        if (!property_exists($options->token, 'can_only_be_used_after')) {
            $options->token->can_only_be_used_after = 'now';
        }
        if (!property_exists($options->token, 'expires_at')) {
            $options->token->expires_at = '+9 hours';
        }
        if (!property_exists($options->token, 'issued_by')) {
            $options->token->issued_by = 'raxon.org';
        }
        if (!property_exists($options, 'refresh')) {
            $options->refresh = (object)[];
            $options->refresh->token = (object)[];
        }
        if (!property_exists($options->refresh->token, 'private_key')) {
            $options->refresh->token->private_key = '{{config(\'project.dir.data\')}}Ssl/RefreshToken_key.pem';
            if (!File::exist($object->config('project.dir.data') . 'Ssl/RefreshToken_key.pem')) {
                //create private key
                //create certificate
                $command = Core::binary($object) .
                    ' raxon_org/basic' .
                    ' openssl' .
                    ' init' .
                    ' -keyout=' . 'RefreshToken_key.pem' .
                    ' -out=' . 'RefreshToken_cert.pem';
                exec($command, $output, $code);
                if ($code !== 0) {
                    throw new Exception('Error creating private key & certificate' . implode(PHP_EOL, $output) . PHP_EOL);
                }
            }
        }
        if (!property_exists($options->refresh->token, 'certificate')) {
            $options->refresh->token->certificate = '{{config(\'project.dir.data\')}}Ssl/RefreshToken_cert.pem';
        }
        if (!property_exists($options->refresh->token, 'passphrase')) {
            $options->refresh->token->passphrase = '';
        }
        if (!property_exists($options->refresh->token, 'issued_at')) {
            $options->refresh->token->issued_at = 'now';
        }
        if (!property_exists($options->refresh->token, 'identified_by')) {
            $options->refresh->token->identified_by = Core::uuid();
        }
        if (!property_exists($options->refresh->token, 'permitted_for')) {
            $options->refresh->token->permitted_for = $permitted_for;
        }
        if (!property_exists($options->refresh->token, 'can_only_be_used_after')) {
            $options->refresh->token->can_only_be_used_after = 'now';
        }
        if (!property_exists($options->refresh->token, 'expires_at')) {
            $options->refresh->token->expires_at = '+48 hours';
        }
        if (!property_exists($options->refresh->token, 'issued_by')) {
            $options->refresh->token->issued_by = 'raxon.org';
        }
        File::write($url_jwt, Core::object($options, Core::OBJECT_JSON));
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
        $user = (object) [
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT, [
                'cost' => 13
            ]),
            'role' => [
                $result['node']->uuid
            ],
            'is' => (object) [
                'active' => 0, //cannot activate immediately
                'created' => $time
            ]
        ];
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
        return $result;
    }


    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function account_create_default($flags, $options): bool|array
    {
        /*
         * - create role ROLE_SYSTEM with rank 1
         * - create role ROLE_ADMIN with rank 2
         */
        Core::interactive();
        $object = $this->object();
        $object->config('raxon.org.node.import.start', microtime(true));
        $url = $object->config('project.dir.data') . 'Account/Role.System.json';
        $data = $object->data_read($url);
        $node = new Node($object);
        $role = $node->role_system();
        $create_many = [];
        $patch_many = [];
        $put_many = [];
        $list = [];
        $error = [];
        $is_transaction = false;
        $is_create = false;
        $is_patch = false;
        $is_put = false;
        $is_lock = false;
        $create = 0;
        $patch = 0;
        $put = 0;
        $skip = 0;
        $double = 0;
        if ($data) {
            $permissions = $data->get('permission');
            if (is_array($permissions)) {
                $name = 'Account.Permission';
                $options_list = $options;
                if (!property_exists($options_list, 'limit')) {
                    $options_list->limit = '*';
                    $options_list->page = 1;
                }
                $response = $node->list($name, $role, $options);
                if ($response) {
                    if (array_key_exists('list', $response)) {
                        foreach ($response['list'] as $item) {
                            $list[$item->name] = $item;
                        }
                    }
                }
                $unique = [];
                foreach ($permissions as $permission) {
                    if (property_exists($permission, 'name')) {
                        if (in_array($permission->name, $unique)) {
                            $logger = false;
                            if ($object->config('framework.environment') === Config::MODE_DEVELOPMENT) {
                                $logger = $object->config('project.log.debug');
                            }
                            if ($logger) {
                                $object->logger($logger)->info('double permission found: ' . $url, ['permission' => $permission->name]);
                            }
                            $double++;
                            continue;
                        }
                        $unique[] = $permission->name;
                        if (array_key_exists($permission->name, $list)) {
                            //put or patch or skip
                            if (property_exists($options, 'force')) {
                                $permission->uuid = $list[$permission->name]->uuid;
                                $put_many[] = $permission;
                                $put++;
                                $is_transaction = true;
                                $is_put = true;
                            } elseif (property_exists($options, 'patch')) {
                                $permission->uuid = $list[$permission->name]->uuid;
                                $patch_many[] = $permission;
                                $patch++;
                                $is_transaction = true;
                                $is_patch = true;
                            } else {
                                $skip++;
                            }
                        } else {
                            //create
                            $create_many[] = $permission;
                            $create++;
                            $is_transaction = true;
                            $is_create = true;
                        }
                    }
                }
                $commit = false;
                if ($is_transaction) {
                    $object->config('raxon.org.node.import.list.count', $create + $put + $patch);
                    $is_lock = $node->startTransaction($name, $options);
                    if ($is_create) {
                        $response = $node->create_many($name, $role, $create_many, [
                            'import' => true,
                            'uuid' => false,
                            'validation' => $options->validation ?? true
                        ]);
                        if (array_key_exists('error', $response)) {
                            $error = array_merge($error, $response['error']);
                        }
                        if (array_key_exists('list', $response)) {
                            $create = count($response['list']);
                        }
                    }
                    if ($is_put) {
                        $response = $node->put_many($name, $role, $put_many, [
                            'import' => true,
                            'validation' => $options->validation ?? true,
                        ]);
                        if (array_key_exists('error', $response)) {
                            $error = array_merge($error, $response['error']);
                        }
                        if (array_key_exists('list', $response)) {
                            $put = count($response['list']);
                        }
                    }
                    if ($is_patch) {
                        $response = $node->patch_many($name, $role, $patch_many, [
                            'import' => true,
                            'validation' => $options->validation ?? true,
                        ]);
                        if (array_key_exists('error', $response)) {
                            $error = array_merge($error, $response['error']);
                        }
                        if (array_key_exists('list', $response)) {
                            $patch = count($response['list']);
                        }
                    }
                    if (!empty($error)) {
                        if ($is_lock) {
                            $node->unlock($name);
                        }
                        return [
                            'error' => $error,
                            'transaction' => true,
                            'duration' => (microtime(true) - $object->config('raxon.org.node.import.start')) * 1000
                        ];
                    } elseif ($is_lock) {
                        $commit = $node->commit($name, $role);
                    }
                }
                $duration = microtime(true) - $object->config('raxon.org.node.import.start');
                $total = $put + $patch + $create;
                $item_per_second = round($total / $duration, 2);
                $object->config('delete', 'node.transaction.' . $name);

                //create role ROLE_SYSTEM
                //create role ROLE_ADMIN

                $roles = [
                    [
                        'name' => 'ROLE_SYSTEM',
                        'rank' => 1,
                        'permission' => '*'
                    ],
                    [
                        'name' => 'ROLE_ADMIN',
                        'rank' => 2,
                        'permission' => '*'
                    ]
                ];
                $name = 'Account.Role';
                $node_list = [];
                foreach ($roles as $roles_role) {
                    $record = $node->record($name, $role, [
                        'filter' => [
                            'name' => $roles_role['name']
                        ]
                    ]);
                    if ($record) {
                        if (array_key_exists('node', $record)) {
                            if (property_exists($options, 'force')) {
                                $output = [];
                                $command = Core::binary($object) .
                                    ' raxon_org/node' .
                                    ' put' .
                                    ' -class=Account.Role' .
                                    ' -uuid=' . $record['node']->uuid .
                                    ' -name=' . $roles_role['name'] .
                                    ' -rank=' . $roles_role['rank'] .
                                    ' -permission=' . $roles_role['permission'];
//                                echo $command . PHP_EOL;
                                exec($command, $output, $code);
                                if ($code === 0) {
                                    $item = Core::object(implode(PHP_EOL, $output), Core::OBJECT_OBJECT);
                                    if ($item) {
                                        $node_list[] = $item;
                                    }
                                }
                            } elseif (property_exists($options, 'patch')) {
                                $output = [];
                                $command = Core::binary($object) .
                                    ' raxon_org/node' .
                                    ' patch' .
                                    ' -class=Account.Role' .
                                    ' -uuid=' . $record['node']->uuid .
                                    ' -name=' . $roles_role['name'] .
                                    ' -rank=' . $roles_role['rank'] .
                                    ' -permission=' . $roles_role['permission'];
//                                echo $command . PHP_EOL;
                                exec($command, $output, $code);
                                if ($code === 0) {
                                    $item = Core::object(implode(PHP_EOL, $output), Core::OBJECT_OBJECT);
                                    if ($node) {
                                        $node_list[] = $item;
                                    }
                                }
                            }
                        } else {
                            throw new Exception('Unknown state detected...');
                        }
                    } else {
                        $output = [];
                        $command = Core::binary($object) .
                            ' raxon_org/node' .
                            ' create' .
                            ' -class=Account.Role' .
                            ' -name=' . $roles_role['name'] .
                            ' -rank=' . $roles_role['rank'] .
                            ' -permission=' . $roles_role['permission'];
//                        echo $command . PHP_EOL;
                        exec($command, $output, $code);
                        if ($code === 0) {
                            $item = Core::object(implode(PHP_EOL, $output), Core::OBJECT_OBJECT);
                            if ($item) {
                                $node_list[] = $item;
                            }
                        }
                    }
                }
                return [
                    'double' => $double,
                    'skip' => $skip,
                    'put' => $put,
                    'patch' => $patch,
                    'create' => $create,
                    'commit' => $commit,
                    'mtime' => File::mtime($url),
                    'duration' => $duration * 1000,
                    'item_per_second' => $item_per_second,
                    'transaction' => true,
                    'role' => $node_list
                ];
            }
        }
        return false;
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function account_create_jwt($flags, $options)
    {
        $object = $this->object();
        $url_jwt = $object->config('project.dir.data') . 'Account/Jwt.json';
        if (File::exist($url_jwt)) {
            if (property_exists($options, 'force')) {
                File::delete($url_jwt);
            } else {
                return false;
            }
        }
        if (!property_exists($options, 'token')) {
            $options->token = (object)[];
        }
        $permitted_for = Core::uuid();
        if (!property_exists($options->token, 'private_key')) {
            $options->token->private_key = '{{config(\'project.dir.data\')}}Ssl/Token_key.pem';
            //create private key
            if (!File::exist($object->config('project.dir.data') . 'Ssl/Token_key.pem')) {
                $command = Core::binary($object) .
                    ' raxon_org/basic' .
                    ' openssl' .
                    ' init' .
                    ' -keyout=' . 'Token_key.pem' .
                    ' -out=' . 'Token_cert.pem';
                exec($command, $output, $code);
                if ($code !== 0) {
                    throw new Exception('Error creating private key & certificate' . implode(PHP_EOL, $output) . PHP_EOL);
                }
            }
        }
        if (!property_exists($options->token, 'certificate')) {
            $options->token->certificate = '{{config(\'project.dir.data\')}}Ssl/Token_cert.pem';
            //create certificate
        }
        if (!property_exists($options->token, 'passphrase')) {
            $options->token->passphrase = '';
        }
        if (!property_exists($options->token, 'issued_at')) {
            $options->token->issued_at = 'now';
        }
        if (!property_exists($options->token, 'identified_by')) {
            $options->token->identified_by = Core::uuid();
        }
        if (!property_exists($options->token, 'permitted_for')) {
            $options->token->permitted_for = $permitted_for;
        }
        if (!property_exists($options->token, 'can_only_be_used_after')) {
            $options->token->can_only_be_used_after = 'now';
        }
        if (!property_exists($options->token, 'expires_at')) {
            $options->token->expires_at = '+9 hours';
        }
        if (!property_exists($options->token, 'issued_by')) {
            $options->token->issued_by = 'raxon.org';
        }
        if (!property_exists($options, 'refresh')) {
            $options->refresh = (object)[];
            $options->refresh->token = (object)[];
        }
        if (!property_exists($options->refresh->token, 'private_key')) {
            $options->refresh->token->private_key = '{{config(\'project.dir.data\')}}Ssl/RefreshToken_key.pem';
            //create private key
            if (!File::exist($object->config('project.dir.data') . 'Ssl/RefreshToken_key.pem')) {
                $command = Core::binary($object) .
                    ' raxon_org/basic' .
                    ' openssl' .
                    ' init' .
                    ' -keyout=' . 'RefreshToken_key.pem' .
                    ' -out=' . 'RefreshToken_cert.pem';
                exec($command, $output, $code);
                if ($code !== 0) {
                    throw new Exception('Error creating private key & certificate' . implode(PHP_EOL, $output) . PHP_EOL);
                }
            }
        }
        if (!property_exists($options->refresh->token, 'certificate')) {
            $options->refresh->token->certificate = '{{config(\'project.dir.data\')}}Ssl/RefreshToken_cert.pem';
            //create certificate
        }
        if (!property_exists($options->refresh->token, 'passphrase')) {
            $options->refresh->token->passphrase = '';
        }
        if (!property_exists($options->refresh->token, 'issued_at')) {
            $options->refresh->token->issued_at = 'now';
        }
        if (!property_exists($options->refresh->token, 'identified_by')) {
            $options->refresh->token->identified_by = Core::uuid();
        }
        if (!property_exists($options->refresh->token, 'permitted_for')) {
            $options->refresh->token->permitted_for = $permitted_for;
        }
        if (!property_exists($options->refresh->token, 'can_only_be_used_after')) {
            $options->refresh->token->can_only_be_used_after = 'now';
        }
        if (!property_exists($options->refresh->token, 'expires_at')) {
            $options->refresh->token->expires_at = '+48 hours';
        }
        if (!property_exists($options->refresh->token, 'issued_by')) {
            $options->refresh->token->issued_by = 'raxon.org';
        }
        File::write($url_jwt, Core::object($options, Core::OBJECT_JSON));
    }

    /**
     * @throws Exception
     */
    public function setup_user($flags, $options): bool|array
    {
        $object = $this->object();
        echo 'This will install user login capabilities to a domain' . PHP_EOL;
        echo 'Press ctrl-c to abort' . PHP_EOL;
        if (!property_exists($options, 'namespace')) {
            throw new Exception('Option namespace required.');
        }
        if (!property_exists($options, 'dir')) {
            throw new Exception('Option dir required. (target domain controller)');
        }
        if (!property_exists($options, 'class')) {
            $options->class = 'User';
        }

        $parse = $this->parse();
//        $parse->storage('options', $options);
        $dir_data = $object->config('project.dir.package') .
            'Raxon' .
            $object->config('ds') .
            'Org' .
            $object->config('ds') .
            'Account' .
            $object->config('ds') .
            'Data' .
            $object->config('ds') .
            'Json' .
            $object->config('ds');
        $dir_template = $object->config('project.dir.package') .
            'Raxon' .
            $object->config('ds') .
            'Org' .
            $object->config('ds') .
            'Account' .
            $object->config('ds') .
            'Data' .
            $object->config('ds') .
            'Php' .
            $object->config('ds');
        if (!property_exists($options, 'data')) {
            $options->data = $dir_data .
                'User' .
                $object->config('extension.json');
        }
        $url_template = $dir_template .
            'Main' .
            $object->config('extension.php') .
            $object->config('extension.tpl');
        $data = $object->parse_read($options->data);
        if ($data) {
            $options = Core::object_merge($data->get('User'), $options);
            $object->data(App::OPTIONS, $options);
        }
        $template = File::read($url_template);
        $response = $parse->compile($template, $parse->storage());
        $url = $options->dir .
            $options->class .
            $object->config('extension.php');
        $size = false;
        if (
            property_exists($options, 'force') &&
            $options->force === true
        ) {
            $size = File::write($url, $response);
        } elseif (
            property_exists($options, 'patch') &&
            $options->patch === true
        ) {
            $size = File::write($url, $response);
        } elseif (File::exist($url)) {
            return false;
        } else {
            $size = File::write($url, $response);
        }
        return [
            'size' => $size
        ];
    }

    /**
     * @throws Exception
     */
    public function user_token($flags, $options)
    {
        if (Handler::method() === Handler::METHOD_CLI) {
            $object = $this->object();
            if (!property_exists($options, 'email')) {
                throw new Exception('Option email required.');
            }
            $email = $options->email;
            $data = [];
            $data[] = Cli::tput('color', Cli::COLOR_GREEN);
            $data[] = Service::token($object, $email);
            $data[] = Cli::tput('reset');
            return new Response(
                $data,
                Response::TYPE_CLI
            );
        }
    }
}