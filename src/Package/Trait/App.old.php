<?php
namespace Package\Raxon\Org\Account\Trait;

use Raxon\Org\App as Application;

trait App
{

    protected ?Application $object;

    public function object($object=null): ?Application
    {
        if($object!==null){
            $this->setObject($object);
        }
        return $this->getObject();
    }

    private function setObject(Application $object): void
    {
        $this->object = $object;
    }

    private function getObject(): ?Application
    {
        return $this->object;
    }
}