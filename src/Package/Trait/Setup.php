<?php
namespace Package\Raxon\Account\Trait;

use Raxon\App;

use Raxon\Exception\DirectoryCreateException;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\File;

use Raxon\Node\Module\Node;

use Exception;
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
}