<?php
namespace Package\Raxon\Account\Trait;

use Raxon\Module\Cli;
use Raxon\Module\Handler;
use Raxon\Module\Response;
use Package\Raxon\Account\Service\User as Service;

use Exception;

use Doctrine\ORM\Exception\ORMException;

use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;
use Raxon\Exception\AuthorizationException;

trait User
{

    /**
     * @throws Exception
     */
    private function user_token(){
        $object = $this->object();
        if(Handler::method() === Handler::METHOD_CLI){
            $email = $object->parameter($object, __FUNCTION__, 1);
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

    /**
     * @throws Exception
     * @throws ORMException
     */
    public function user_login(){
        $object = $this->object();
        if (Handler::method() === 'POST') {
            $data = Service::login($object);
            return new Response(
                $data,
                Response::TYPE_JSON
            );
        }
    }

    /**
     * @throws AuthorizationException
     * @throws ObjectException
     * @throws FileWriteException
     * @throws Exception
     */
    public function user_current()
    {
        $object = $this->object();
        if (Handler::method() === 'GET') {
            $data = Service::current($object);
            return new Response(
                $data,
                Response::TYPE_JSON
            );
        }
    }

    /**
     * @throws AuthorizationException
     * @throws Exception
     */
    public function user_refresh_token(){
        $object = $this->object();
        if (Handler::method() === 'GET') {
            $data =  Service::refresh_token($object);
            return new Response(
                $data,
                Response::TYPE_JSON
            );
        }
    }

}