<?php
namespace Plugin;

use Package\Raxon\Account\Module\User;

use Raxon\Exception\AuthorizationException;
use Raxon\Exception\FileWriteException;
use Raxon\Exception\ObjectException;

trait User_Token_Id {

    /**
     * @throws AuthorizationException
     * @throws FileWriteException
     * @throws ObjectException
     */
    public function user_token_id(): null | int | string
    {
        $object = $this->object();
        $user = User::get_by_authorization($object);
        if($user){
            return $user->getId();
        }
        return null;
    }

}