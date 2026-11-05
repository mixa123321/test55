<?php

namespace app\core\helpers\headers;

use app\core\entities\User;
use app\core\Permission;

class SystemSideBar extends SideBar
{
    /** @var $user User */
    private $user;
    /** @var $permission Permission */
    private $permission;

    public function __construct(Permission $permission)
    {
        $this->permission = $permission;
        $this->user = $permission->getUser();
        $this->setHead('logo_mask.png');
    }

    public function render()
    {
        $delivery_routes = $this->createSubMenuArray([
            'system_settings/carriers' => ['Carriers', true],
            'system_settings/return_carriers' => ['Return Carriers', true],
        ], null);
        $advanced_routes = $this->createSubMenuArray([
            'advanced/languages' => ['Languages', true],
            0 => null,
            'advanced/routes' => ['Routes', true],
            'advanced/roles' => ['Roles', true],
            'advanced/user_groups' => ['User Groups', true],
        ], null);
        $this
            ->addItem('Logistic Companies', 'road')
            ->addItem('Delivery', 'truck', $delivery_routes)
            ->addItem('Advanced', 'user', $advanced_routes);
    }
}