<?php
namespace Package\Raxon\Account\Trait;

use Exception;
use Raxon\App;
use Raxon\Config;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\File;
use Raxon\Module\Dir;
use Raxon\Node\Module\Node;

trait Setup {

    /**
     * @throws DirectoryCreateException
     * @throws ObjectException
     * @throws FileWriteException
     */
    public function import_role_system(): void
    {
        $object = $this->object();
        $package = $object->request('package');
        if($package){
            $node = new Node($object);
            $node->role_system_create($package);
        }
    }

     /**
     * @throws Exception
     */
    public function register(): bool
    {
        $object = $this->object();
        $status = false;
        $options = App::options($object);
        $node = new Node($object);
        $record_options = [
            'where' => [
                [
                    'value' => $object->request('package'),
                    'attribute' => 'name',
                    'operator' => '===',
                ]
            ]
        ];
        $class = 'System.Installation';
        $response = $node->record($class, $node->role_system(), $record_options);
        if(
            $response &&
            array_key_exists('node', $response)
        ){
            if(property_exists($options, 'force')){
                $record = $response['node'];
                $record->mtime = time();
                $response = $node->put($class, $node->role_system(), $record);
                echo 'Register update ' . $object->request('package') . ' installation...' . PHP_EOL;
                $status = true;
            }
            elseif(property_exists($options, 'patch')){
                $record = $response['node'];
                $record->mtime = time();
                $response = $node->patch($class, $node->role_system(), $record);
                echo 'Register update ' . $object->request('package') . ' installation...' . PHP_EOL;
                $status = true;
            }
            else {
                echo 'Skipping ' . $object->request('package') . ' installation...' . PHP_EOL;
            }
        } else {
            $time = time();
            $record = (object) [
                'name' => $object->request('package'),
                'ctime' => $time,
                'mtime' => $time,
            ];
            $response = $node->create($class, $node->role_system(), $record);
            echo 'Registering ' . $object->request('package') . ' installation...' . PHP_EOL;
            $status = true;
        }
        return $status;
    }

    public function role_user_create(object $flags, object $options): void
    {
        $object = $this->object();
        $url = $object->config('project.dir.vendor') . 'raxon/account/Data/Role.User.json';        
        $options_import = clone $options;
        $options_import->url = $url;
        unset($options_import->wildcard);
        $this->role_import($flags, $options_import);
    }

    public function role_system_create(object $flags, object $options): void
    {
        $object = $this->object();
        $url = $object->config('project.dir.vendor') . 'raxon/account/Data/Role.System.json';
        $options_import = clone $options;
        $options_import->url = $url;
        $options_import->wildcard = '*';
        $this->role_import($flags, $options_import);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function role_import($flags, $options): void
    {
        $object = $this->object();
        if(!property_exists($options, 'url')){
            throw new Exception('Option url required');
        }
        $url = $options->url;
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
                    is_array($response) &&
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
                    $permission_array[] = $response['node']->uuid;
                }
            }
        }
        $response = $node->record('Account.Role', $node->role_system(), [
            'filter' => [
                'name' => $data->get('name')
            ]
        ]);
        if(property_exists($options, 'wildcard')){
            $permission_array = '*';
        }
        if(
            is_array($response) &&
            array_key_exists('node', $response) &&
            property_exists($response['node'], 'uuid')
        ){
            $role = $response['node'];
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
            if(property_exists($role, 'uuid')){
                echo $role->name . ' reset...' . PHP_EOL;
            }
        } else{
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
            if(property_exists($role, 'uuid')){
                echo $role->name . ' created...' . PHP_EOL;
            }
        }
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function default_create(object $flags, object $options): bool|array
    {
        /*
         * - create role ROLE_SYSTEM with rank 1
         * - create role ROLE_ADMIN with rank 2
         * - create role ROLE_ANONYMOUS with rank 9.999.999 and link its permissions
         * - collect anonymous roles, currently 3 for the admin / api.
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
                                    ' raxon/node' .
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
                                    ' raxon/node' .
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
                            ' raxon/node' .
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
    public function jwt_create($flags, $options): bool
    {
        $object = $this->object();
        $url_jwt = $object->config('project.dir.data') . 'Account/Jwt.json';
        $options_jwt = (object) [];
        if (File::exist($url_jwt)) {
            if (property_exists($options, 'force')) {
                File::delete($url_jwt);
            }
            else {
                echo 'Skipping jwt creation, use option -force to create a new jwt...' . PHP_EOL;
                return false;
            }
        }
        if (!property_exists($options, 'token')) {
            $options_jwt->token = (object)[];
            $options->token = (object)[];
        } else {
            $options_jwt->token = $options->token;
        }
        $permitted_for = Core::uuid();
        if (!property_exists($options->token, 'private_key')) {
            $options_jwt->token->private_key = '{{config(\'project.dir.data\')}}Ssl/Token_key.pem';
            //create private key
            if (!File::exist($object->config('project.dir.data') . 'Ssl/Token_key.pem')) {
                $command = Core::binary($object) .
                    ' raxon/basic' .
                    ' openssl' .
                    ' init' .
                    ' -keyout=' . 'Token_key.pem' .
                    ' -out=' . 'Token_cert.pem';
                exec($command, $output, $code);
                if ($code !== 0) {
                    throw new Exception('Error creating private key & certificate' . implode(PHP_EOL, $output) . PHP_EOL);
                }
            }
        } else {
            $options_jwt->token->private_key = $options->token->private_key;
        }
        if (!property_exists($options->token, 'certificate')) {
            $options_jwt->token->certificate = '{{config(\'project.dir.data\')}}Ssl/Token_cert.pem';
            //create certificate
        } else {
            $options_jwt->token->certificate = $options->token->certificate;
        }
        if (!property_exists($options->token, 'passphrase')) {
            $options_jwt->token->passphrase = '';
        } else {
            $options_jwt->token->passphrase = $options->token->passphrase;
        }
        if (!property_exists($options->token, 'issued_at')) {
            $options_jwt->token->issued_at = 'now';
        } else {
            $options_jwt->token->issued_at = $options->token->issued_at;
        }
        if (!property_exists($options->token, 'identified_by')) {
            $options_jwt->token->identified_by = Core::uuid();
        } else {
            $options_jwt->token->identified_by = $options->token->identified_by;
        }
        if (!property_exists($options->token, 'permitted_for')) {
            $options_jwt->token->permitted_for = $permitted_for;
        } else {
            $options_jwt->token->permitted_for = $options->token->permitted_for;
        }
        if (!property_exists($options->token, 'can_only_be_used_after')) {
            $options_jwt->token->can_only_be_used_after = 'now';
        } else {
            $options_jwt->token->can_only_be_used_after = $options->token->can_only_be_used_after;
        }
        if (!property_exists($options->token, 'expires_at')) {
            $options_jwt->token->expires_at = '+9 hours';
        } else {
            $options_jwt->token->expires_at = $options->token->expires_at;
        }
        if (!property_exists($options->token, 'issued_by')) {
            $options_jwt->token->issued_by = 'raxon.org';
        } else {
            $options_jwt->token->issued_by = $options->token->issued_by;
        }
        if (!property_exists($options, 'refresh')) {
            $options_jwt->refresh = (object)[];
            $options_jwt->refresh->token = (object)[];
            $options->refresh = (object)[];
            $options->refresh->token = (object)[];
        } else {            
            $options_jwt->refresh->token = $options->refresh->token ?? (object)[];
        }
        if (!property_exists($options->refresh->token, 'private_key')) {
            $options_jwt->refresh->token->private_key = '{{config(\'project.dir.data\')}}Ssl/RefreshToken_key.pem';
            //create private key
            if (!File::exist($object->config('project.dir.data') . 'Ssl/RefreshToken_key.pem')) {
                $command = Core::binary($object) .
                    ' raxon/basic' .
                    ' openssl' .
                    ' init' .
                    ' -keyout=' . 'RefreshToken_key.pem' .
                    ' -out=' . 'RefreshToken_cert.pem';
                exec($command, $output, $code);
                if ($code !== 0) {
                    throw new Exception('Error creating private key & certificate' . implode(PHP_EOL, $output) . PHP_EOL);
                }
            }
        } else {
            $options_jwt->refresh->token->private_key = $options->refresh->token->private_key;
        }
        if (!property_exists($options->refresh->token, 'certificate')) {
            $options_jwt->refresh->token->certificate = '{{config(\'project.dir.data\')}}Ssl/RefreshToken_cert.pem';
            //create certificate
        } else {
            $options_jwt->refresh->token->certificate = $options->refresh->token->certificate;
        }
        if (!property_exists($options->refresh->token, 'passphrase')) {
            $options_jwt->refresh->token->passphrase = '';
        } else {
            $options_jwt->refresh->token->passphrase = $options->refresh->token->passphrase;
        }
        if (!property_exists($options->refresh->token, 'issued_at')) {
            $options_jwt->refresh->token->issued_at = 'now';
        } else {
            $options_jwt->refresh->token->issued_at = $options->refresh->token->issued_at;
        }
        if (!property_exists($options->refresh->token, 'identified_by')) {
            $options_jwt->refresh->token->identified_by = Core::uuid();
        } else {
            $options_jwt->refresh->token->identified_by = $options->refresh->token->identified_by;
        }
        if (!property_exists($options->refresh->token, 'permitted_for')) {
            $options_jwt->refresh->token->permitted_for = $permitted_for;
        } else {
            $options_jwt->refresh->token->permitted_for = $options->refresh->token->permitted_for;
        }
        if (!property_exists($options->refresh->token, 'can_only_be_used_after')) {
            $options_jwt->refresh->token->can_only_be_used_after = 'now';
        } else {
            $options_jwt->refresh->token->can_only_be_used_after = $options->refresh->token->can_only_be_used_after;
        }
        if (!property_exists($options->refresh->token, 'expires_at')) {
            $options_jwt->refresh->token->expires_at = '+48 hours';
        } else {
            $options_jwt->refresh->token->expires_at = $options->refresh->token->expires_at;
        }
        if (!property_exists($options->refresh->token, 'issued_by')) {
            $options_jwt->refresh->token->issued_by = 'raxon.org';
        } else {
            $options_jwt->refresh->token->issued_by = $options->refresh->token->issued_by;
        }    
        $bytes = File::write($url_jwt, Core::object($options_jwt, Core::OBJECT_JSON));
        echo 'Written: ' . $url_jwt . ' size:  ' . File::size_format($bytes) . PHP_EOL;
        return true;
    }

    public function schema_import($flags, $options): void
    {
        $object = $this->object();
        $dir_schema = $object->config('project.dir.package') . 'Raxon/Account/Schema/';
        $dir = new Dir();
        $read = $dir->read($dir_schema);
        if($read){
            foreach($read as $file){
                $command = Core::binary($object) . ' raxon/schema' . ' import' . ' -file=' . $file;
            }
        }
        ddd($read);
    }
}