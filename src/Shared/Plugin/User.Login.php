<?php
namespace Plugin;

use Doctrine\ORM\Exception\ORMException;

use Exception;

use Package\Raxon\Account\Module\User;

trait User_Login
{

    /**
     * @throws Exception
     * @throws ORMException
     */
    protected function user_login(string $email='', string $password=''): mixed
    {
        $object = $this->object();
        $object->request('email', $email);
        $object->request('password', $password);

        return User::login($object, $object->request());
    }
}