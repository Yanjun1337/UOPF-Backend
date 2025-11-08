<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Model\User;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Interface\Endpoint;

/**
 * Users ID
 */
final class UsersId extends Endpoint {
    public function read(Response $response): User {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        if ($user = UserManager::fetchEntry($id))
            return $user;
        else
            $this->throwNotFoundException();
    }
}
