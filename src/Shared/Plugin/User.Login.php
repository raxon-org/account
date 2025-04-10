<?php
namespace Plugin;

use Exception;

use Package\Raxon\Account\Service\User;

trait User_Login
{

    /**
     * @throws Exception
     */
    protected function user_login(string $email='', string $password=''): mixed
    {
        $object = $this->object();
        $object->request('email', $email);
        $object->request('password', $password);

        $user = User::login($object);
        d($user);
        return false;
    }
}