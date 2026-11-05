<?php

namespace app\backend\controllers;

use app\backend\models\Roles;
use app\backend\models\Routes;
use app\backend\models\UserGroups;
use app\backend\models\Users as UsersModel;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\backend\models\Accesses;
use app\core\models\RouteHistories;
use app\core\services\RouteService;

class RouteAccesses extends AuthController
{
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        (new RouteService())->execute();
    }

    /** @throws */
    public function routeDetail()
    {
        $back = $this->route('advanced',Accounts::class);
        if (empty($route = Routes::get($_GET['route_id'] ?? null))) {
            View::addFlash('route_accesses', 'danger', 'Route not found');
            throw new RedirectException($back);
        }
        if (!empty($parent_id = $route->getParentId()))
            $back = $this->route('routeDetail', null, [
                'route_id' => $parent_id
            ]);
        $parent_route = null;
        $head_route = Routes::getHeadRoute($route);
        if (!empty($route->getParentId())) {
            $parent_route = Routes::get($route->getParentId());
            if (!empty($parent_route) && $route->isEditable()) {
                $route->setEditable(false);
                $route->save(['id', 'editable']);
            }
        }
        $data = [
            'route' => $route,
            'parent_route' => $parent_route,
            'destroy_access' => !$route->isActive() && $this->user->hasDevAccess(),
            'route_children' => Routes::getChildren($route->getId()),
            'roles' => Roles::getList(),
            'user_groups' => UserGroups::getList(),
            'users' => Accesses::getAccessedUsersForRoute($head_route->getId()),
            'current_accesses' => [
                'Roles' => Accesses::getAccessesByRoles($head_route->getId()),
                'UserGroups' => Accesses::getAccessesByUserGroups($head_route->getId()),
                'Users' => Accesses::getAccessesByUsers($head_route->getId())
            ],
            'back' => $back,
        ];
        $this->render($data, 'routes/route_detail');
    }

    public function setRouteAccess()
    {
        if (empty($type = $_POST['type'] ?? null))
            return $this->response = ['result' => 'error', 'message' => 'Type not found'];
        if (empty($route = Routes::get($_POST['route_id'] ?? null)))
            return $this->response = ['result' => 'error', 'message' => 'Route not found'];
        if (!$route->isEditable())
            return $this->response = ['result' => 'error', 'message' => 'Route not editable'];
        switch ($type) {
            case 'Roles':
                if (empty($role = Roles::getById(Request::getPositiveId($_POST, 'object_id'))))
                    return $this->response = ['result' => 'error', 'message' => 'Role not found'];
                Accesses::setRoleAccess($route->getId(), $role->getId(), $_POST['access_id'] ?? null, $this->getAuthUserId());
                break;
            case 'UserGroups':
                if (empty($user_group = UserGroups::getById(Request::getPositiveId($_POST, 'object_id'))))
                    return $this->response = ['result' => 'error', 'message' => 'User group not found'];
                Accesses::setUserGroupAccess($route->getId(), $user_group->getId(), $_POST['access_id'] ?? null, $this->getAuthUserId());
                break;
            case 'Users':
                if (empty($user = UsersModel::getById(Request::getPositiveId($_POST, 'object_id'))))
                    return $this->response = ['result' => 'error', 'message' => 'User not found'];
                Accesses::setUserAccess($route->getId(), $user->getId(), $_POST['access_id'] ?? null, $this->getAuthUserId());
                break;
            default:
                return $this->response = ['result' => 'error', 'message' => 'Undefined type'];
        }

        return $this->response = ['result' => 'success', 'message' => 'Access changed'];
    }

    public function clearRouteUserAccess()
    {
        if (empty($route = Routes::get(Request::getPositiveId($_POST, 'route_id'))))
            return $this->response = ['result' => 'error', 'message' => 'Route not found'];
        if (empty($user = UsersModel::getById(Request::getPositiveId($_POST, 'user_id'))))
            return $this->response = ['result' => 'error', 'message' => 'User not found'];
        Accesses::removeRouteUserAccess($route->getId(), $user->getId(), $this->getAuthUserId());
        return $this->response = ['result' => 'success'];
    }

    /** @throws */
    public function changeRoute()
    {
        if ($this->user->hasDevAccess()) {
            if (empty($old_route = Routes::get(Request::getPositiveId($_POST, 'route_id')))) {
                View::addFlash('change_route', 'danger', 'Route error');
                throw new RedirectException($this->route('advanced', Accounts::class));
            }
            $route = clone $old_route;
            $fields_to_save = ['id', 'editable', 'virtual_access'];
            if ($route->getAlias() !== $_POST['alias']) {
                $route->setAlias(trim($_POST['alias']));
                $fields_to_save[] = 'alias';
            }
            $route->setEditable(!empty($_POST['editable']));
            $route->setVirtualAccess(!empty($_POST['virtual_access']));
            $route->save($fields_to_save);
            RouteHistories::update($route, $old_route, $this->getAuthUserId());
            View::addFlash('change_route', 'success', 'Updated');
        }
        throw new RedirectException($this->route('routeDetail', self::class, ['route_id' => $route->getId()]));
    }

    /** @throws */
    public function destroy()
    {
        if (!$this->user->hasDevAccess())
            throw new RedirectException('/');
        $back = $this->route('advanced', Accounts::class);
        if (empty($route = Routes::get(Request::getPositiveId($_POST, 'route_id')))) {
            View::addFlash('destroy', 'danger', 'Route error');
            throw new RedirectException($back);
        }
        Routes::destroySettings($route->getId());
        $route->delete();
        RouteHistories::delete($route, $this->getAuthUserId());
        View::addFlash('destroy', 'warning', 'Route destroyed');
        throw new RedirectException($back);
    }

    public function findChildRoute()
    {
        if ($this->user->hasDevAccess() && !empty($route = Routes::get(Request::getPositiveId($_POST, 'route_id')))) {
            $query = $_POST['nameStartsWith'] ?? null;
            if (!empty($query) && strlen($query) > 1)
                return $this->response = Arr::maps(Routes::findChildRoute($route->getId(), $query), 'id', ['name', 'alias']);
        }
        return $this->response = [];
    }

    public function setParentRoute()
    {
        if (!$this->user->hasDevAccess())
            return $this->response = ['result' => 'error', 'message' => 'Access denied'];
        if (empty($route = Routes::get($_POST['route_id'] ?? null)))
            return $this->response = ['result' => 'error', 'message' => 'Route not found'];
        if (empty($child_route = Routes::get($_POST['child_route_id'] ?? null)))
            return $this->response = ['result' => 'error', 'message' => 'Child route not found'];
        if ($route->getId() == $child_route->getId())
            return $this->response = ['result' => 'error', 'message' => 'Data error'];
        if (in_array($child_route->getId(), Routes::getHeadsRoute($route)))
            return $this->response = ['result' => 'error', 'message' => 'Child route found in head hierarchic'];
        $child_route->setParentId($route->getId());
        $child_route->setEditable(false);
        $child_route->save(['id', 'parent_id', 'editable']);
        RouteHistories::addChild($route, $child_route, $this->getAuthUserId());
        return $this->response = ['result' => 'success'];
    }

    public function clearParentRoute()
    {
        if (!$this->user->hasDevAccess())
            return $this->response = ['result' => 'error', 'message' => 'Access denied'];
        if (empty($route = Routes::get($_POST['route_id'] ?? null)))
            return $this->response = ['result' => 'error', 'message' => 'Route not found'];
        if (empty($child_route = Routes::get($_POST['child_route_id'] ?? null)))
            return $this->response = ['result' => 'error', 'message' => 'Child route not found'];
        if ($child_route->getParentId() != $route->getId())
            return $this->response = ['result' => 'error', 'message' => 'Data error'];
        $child_route->setParentId('');
        $child_route->save(['id', 'parent_id']);
        RouteHistories::removeChild($route, $child_route, $this->getAuthUserId());
        return $this->response = ['result' => 'success'];
    }
}
