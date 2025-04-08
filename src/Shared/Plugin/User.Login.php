<?php
namespace Plugin;

use Exception;

use Raxon\Module\Core;

trait User_Login
{

    /**
     * @throws Exception
     */
    protected function user_login(string $user='', string $password=''): mixed
    {
        $object = $this->object();
        d($user);
        d($password);
        d('now return user object');
    }
}