<?php

namespace app\core;

use app\core\entities\Perm;
use app\core\entities\Role;
use app\core\entities\Route;
use app\core\entities\User;
use app\core\interfaces\AccessPermissionEntity;
use app\core\models\perms\Accesses;
use app\core\models\perms\Roles;
use app\core\models\perms\UserGroups;
use app\core\models\Routes;

class Permission
{

    /** @var User */
    private $user;
    /** @var Role */
    private $role;

    private $is_virtual;

    /** @var Perm */
    private $perm;

    /** @var bool */
    private $error = false;

    public function __construct(User $user, $is_virtual = false)
    {
        $this->user = $user;
        $this->role = Roles::getById($user->getRoleId());

        $this->is_virtual = (bool)$is_virtual;

        $this->perm = new Perm();
    }

    private function handle(Route $route)
    {
        if ($this->error)
            return;

        $role_access = Accesses::getRoleAccess($route->getId(), $this->role->getId());
        $user_select = false;
        if (!empty($role_access)) {
            if (Accesses::isReject($role_access->getAccess())) {
                $this->perm->setReject();
                return;
            }
            $user_select = Accesses::isUserSelect($role_access->getAccess());
        }
        if (!empty($user_access = Accesses::getUserAccess($route->getId(), $this->user->getId())))
            $this->handleAccess($user_access);
        if ($user_select)
            return;
        if ($this->role->hasUserGroups())
            $this->getUserGroupsAccess($route);
        if (!empty($role_access))
            $this->handleAccess($role_access);
    }

    private function getUserGroupsAccess(Route $route): void
    {
        $user_groups = UserGroups::getActiveGroupIdsByUserId($this->user->getId());
        if (empty($user_groups))
            return;
        $has_access = false;
        $has_only_read = false;
        foreach ($user_groups as $user_group_id) {
            $user_group_access = Accesses::getUserGroupAccess($route->getId(), $user_group_id);
            if (empty($user_group_access))
                continue;
            switch ($user_group_access->getAccess()):
                case Accesses::PERM_ACCESS:
                    $has_access = true;
                    break;
                case Accesses::PERM_READ:
                    $has_only_read = true;
                    break;
                case Accesses::PERM_REJECT:
                    $this->perm->setReject();
                    return;
            endswitch;
        }
        if ($has_access)
            $this->perm->setAccess();

        if ($has_only_read)
            $this->perm->setOnlyRead();
    }

    private function handleAccess(AccessPermissionEntity $access): void
    {
        switch ($access->getAccess()):
            case Accesses::PERM_ACCESS:
                $this->perm->setAccess();
                return;
            case Accesses::PERM_REJECT:
                $this->perm->setReject();
                return;
            case Accesses::PERM_READ:
                $this->perm->setOnlyRead();
                return;
        endswitch;
    }

    public function get(Route $route = null): Perm
    {
        $this->perm->reset();
        if (empty($route) || !$route->isActive())
            return $this->perm;

        if (!empty($stored_perm = PermissionSessionStore::get($route->getId(), $this->user->getId()))) {
            $this->perm = $stored_perm;
        } else {
            $this->handle(Routes::getHeadRoute($route));
            PermissionSessionStore::set($route->getId(), $this->user->getId(), $this->perm);
        }
        return $this->perm;
    }

    public function isAccess(Route $route = null): bool
    {
        if (empty($route))
            return false;
        if ($this->is_virtual && !$route->isVirtualAccess())
            return false;
        $this->get($route);
        if ($this->perm->getReject())
            return false;
        return $this->perm->getAccess() || $this->perm->getOnlyRead();
    }

    public function isAccessByUrl($route_str): bool
    {
        if (empty($route = Routes::find($route_str)))
            return false;
        return $this->isAccess($route);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isVirtual(): bool
    {
        return $this->is_virtual;
    }

    public static function getSimpleAccess($route_str, User $user, $warehouse_virtual): bool
    {
        if (empty($route = Routes::find($route_str)))
            return false;

        $perm = new self($user, $warehouse_virtual);
        return $perm->isAccess($route);

    }
}