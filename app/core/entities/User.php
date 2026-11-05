<?php

namespace app\core\entities;

use app\backend\models\Roles;
use app\core\db\Mysql;
use app\core\interfaces\AccessObjectEntity;
use app\core\services\crypto\StoredSecret;

class User extends Entity implements AccessObjectEntity
{
    static $developers = [];

    const USER_STATUS_ACTIVE = 1;
    const USER_STATUS_BLOCKED = 0;

    const ID__SYSTEM = -1;

    protected $id;
    protected $parent_id;
    protected $login;
    protected $api_key;
    protected $settings = [];
    protected $status = self::USER_STATUS_ACTIVE;
    protected $pass;
    protected $cart_id;
    protected $warehouse_id;
    protected $role_id;
    protected $lang_id;

    public static $STATUSES = [
        self::USER_STATUS_BLOCKED => 'Not Active',
        self::USER_STATUS_ACTIVE => 'Active'
    ];

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getParentId()
    {
        return $this->parent_id;
    }

    public function setParentId($parent_id)
    {
        $this->parent_id = (int)$parent_id;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function setLogin($login)
    {
        $this->login = (string)$login;
    }

    public function getName(): string
    {
        return (string)$this->getLogin();
    }

    /**
     * The DB value is encrypted at rest by StoredSecret (or still plaintext before migration). It is kept as it is and
     * decrypted only on use: users are loaded on every request, most of which never need the key.
     */
    public function getApiKey(): string
    {
        return (string)StoredSecret::decrypt($this->api_key);
    }

    public function setApiKey($api_key)
    {
        $this->api_key = (string)$api_key;
    }

    public function hasApiKey(): bool
    {
        return $this->api_key !== null && $this->api_key !== '';
    }

    public function isApiKeyValid($api_key)
    {
        return $this->hasApiKey() && is_string($api_key) && hash_equals($this->getApiKey(), $api_key);
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Warehouse settings of the user (Retain.me printer, picking state), stored as JSON in users.settings
     */
    public function setSettings($settings)
    {
        if (is_string($settings))
            $settings = json_decode($settings, true);
        $this->settings = is_array($settings) ? $settings : [];
    }

    public function getRetainMePrinter()
    {
        return empty($this->settings['retain_me']) ? null : $this->settings['retain_me'];
    }

    public function setRetainMePrinter($source_id)
    {
        $this->settings['retain_me'] = $source_id;
    }

    public function getPickingData()
    {
        if (empty($this->settings['picking']))
            return null;
        $part_picking = explode('|', $this->settings['picking']);
        if (count($part_picking) < 3)
            return null;
        $res = ['type_id' => $part_picking[0], 'zone' => $part_picking[1], 'date' => $part_picking[2]];
        if (count($part_picking) == 4)
            $res['mezzanine'] = $part_picking[3];
        return $res;
    }

    public function setPickingData($picking_type_id, $picking_zone, $picking_date, $mezzanine = null)
    {
        $this->settings['picking'] = "$picking_type_id|$picking_zone|$picking_date";
        if (!is_null($mezzanine))
            $this->settings['picking'] .= "|$mezzanine";
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = (int)$status;
    }

    public function isActive(): bool
    {
        return (int)$this->getStatus() === self::USER_STATUS_ACTIVE;
    }

    public function isBlocked(): bool
    {
        return (int)$this->getStatus() === self::USER_STATUS_BLOCKED;
    }

    public function getPass()
    {
        return $this->pass;
    }

    public function setPass($pass)
    {
        $this->pass = (string)$pass;
    }

    public function hashPass()
    {
        if (empty($this->pass))
            return;
        $this->pass = password_hash($this->pass, PASSWORD_BCRYPT);
    }

    public function getCartId()
    {
        return $this->cart_id;
    }

    public function setCartId($id)
    {
        $this->cart_id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getRoleId(): int
    {
        return (int)$this->role_id;
    }

    public function setRoleId($role_id): void
    {
        $this->role_id = (int)$role_id;
    }

    public function getLangId()
    {
        return $this->lang_id;
    }

    public function setLangId($id)
    {
        $this->lang_id = (int)$id;
    }

    public function isWarehouseSide(): bool
    {
        return Roles::inWarehouseSide($this->getRoleId());
    }

    public function isClientSide(): bool
    {
        return Roles::inClientSide($this->getRoleId());
    }

    public function isRole($role_id): bool
    {
        return  $this->getRoleId() === (int)$role_id;
    }

    public function isRoleSystem(): bool
    {
        return $this->isRole(Roles::SYSTEM);
    }

    public function isRoleSA(): bool
    {
        return $this->isRole(Roles::SA);
    }

    public function isRoleManager(): bool
    {
        return $this->isRole(Roles::MANAGER);
    }

    public function isRoleWorker(): bool
    {
        return $this->isRole(Roles::WORKER);
    }

    public function isRoleSupplierAdmin(): bool
    {
        return $this->isRole(Roles::SUPPLIER_ADMIN);
    }

    public function isRoleSupplierUser(): bool
    {
        return $this->isRole(Roles::SUPPLIER_USER);
    }

    public function isRoleAdmins(): bool
    {
        return $this->isRoleSA() || $this->isRoleSupplierAdmin();
    }

    public function isSystem(): bool
    {
        return $this->getId() === self::ID__SYSTEM;
    }

    public function hasUserAccess($user_id, $parent_id): bool
    {
        return
            //if it's the same user
            $this->getId() === $user_id
            //if it's a parent user
            || ($this->getId() !== $user_id && $this->getId() === $parent_id)
            //if this user is in the same group and current user has ACCESS_USERS flag
            || $this->isRoleSA();
    }

    public function hasDevAccess(): bool
    {
        return $this->isRoleSA() && in_array($this->login, self::$developers);
    }

    public function hasAdminsAccess(): bool                                   // AMS
    {
        return Roles::isAdmin($this->getRoleId());
    }

    public function hasWorkerAccess(): bool                                 // WS
    {
        return $this->isRoleSA()
            || $this->isRoleManager()
            || $this->isRoleWorker();
    }

    public function hasManagerAccess(): bool
    {
        return Roles::isManager($this->getRoleId());
    }

    public function hasAdminAccess(): bool
    {
        return $this->isRoleSA() || $this->isRoleSupplierAdmin();
    }

    public function hasAllAccess(): bool                                      // UAWMS
    {
        return $this->hasSupplierAccess() || $this->hasWorkerAccess();
    }

    public function hasUsersAccess(): bool
    {
        return $this->isRoleSupplierAdmin() || $this->isRoleSA();
    }

    public function hasWorkersAccess(): bool
    {
        return $this->isRoleManager() || $this->isRoleSA();
    }

    public function hasSupplierAccess(): bool
    {
        return $this->isRoleSA() || $this->isRoleSupplierAdmin() || $this->isRoleSupplierUser();
    }

    public function hasMultipleWarehouse(): bool
    {
        return Roles::hasMultipleWarehouse($this->getRoleId());
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save) || $this->isSystem())
            return;
        $this->hashPass();
        $data = $this->toArray();
        if (empty($data['pass']))
            unset($data['pass']);
        $data['settings'] = empty($data['settings']) ? null : json_encode($data['settings']);
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $data = StoredSecret::encryptFields($data, ['api_key']);
        $id = Mysql::saveRow('users', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'parent_id' => $this->getParentId(),
            'login' => $this->getLogin(),
            'api_key' => $this->api_key,
            'settings' => $this->getSettings(),
            'status' => $this->getStatus(),
            'pass' => $this->getPass(),
            'cart_id' => $this->getCartId(),
            'warehouse_id' => $this->getWarehouseId(),
            'role_id' => $this->getRoleId(),
            'lang_id' => $this->getLangId(),
        ];
    }
}