<?php
namespace Package\Raxon\Org\Account\Trait;

use Raxon\Org\App;

use Raxon\Org\Exception\DirectoryCreateException;
use Raxon\Org\Exception\FileWriteException;
use Raxon\Org\Exception\ObjectException;
use Raxon\Org\Module\Core;
use Raxon\Org\Module\File;

use Raxon\Org\Node\Model\Node;

use Exception;
trait Import {

    /**
     * @throws DirectoryCreateException
     * @throws ObjectException
     * @throws FileWriteException
     */
    public function role_system(): void
    {
        $object = $this->object();
        $package = $object->request('package');
        if($package){
            $node = new Node($object);
            $node->role_system_create($package);
        }
    }
}