<?php

namespace app\core\helpers\headers;

use app\core\entities\LogisticCompany;
use app\core\entities\User;
use app\core\Environment;
use app\core\models\Users;
use app\core\Permission;

class UserSideBar extends SideBar
{
    /** @var User */
    private $user;
    /** @var Permission */
    private $permission;
    /** @var bool */
    private $is_virtual;

    public function __construct(LogisticCompany $logistic_company, Permission $permission, $lang)
    {
        $this->permission = $permission;
        $this->user = $permission->getUser();
        $this->is_virtual = $permission->isVirtual();
        $this->setTitleItems($lang);
        $addition = '';
        if ($this->user->isRoleSupplierUser() && !empty($logo = Users::getLogoForUser($this->user->getId())))
            $addition = '<img style="max-height: 30px; margin-top: 3px; padding: 0; left: 110px; top: 5px; position: absolute;"
                              title="logo"
                              src="' . $logo . '"/>';
        $this->setHead($logistic_company->getLogo(), $addition);
    }

    public function render()
    {
        if (!$this->user->isRoleWorker())
            $this
                ->addItem('Dashboard', 'tachometer-alt')
                ->addSeparator();
        // Stock
        if ($this->user->hasAllAccess()) {
            $this->addHeading('Stock');
            $stock_routes = $this->createSubMenuArray([
                'goods_in' => ['Incoming (calendar)', !$this->user->isRoleSupplierUser() && !$this->user->isRoleWorker()],
                'goods_in/all' => [($this->user->isRoleSupplierUser() ? 'Booked ' : '') . 'ASNs', !$this->user->isRoleWorker()],
                'goods_in/ncrs' => ['NCRs', !$this->user->isRoleWorker()],
                'return_ASNs' => ['Return ASNs', null],
                0 => null,
                'goods_in/create_asn' => ['Create ASN', $this->user->hasSupplierAccess() || $this->user->hasManagerAccess()],
                'goods_in/create_return_asn' => ['Create Return ASN', null],
                1 => null,
                'goods_in/arrival' => ['Goods Arrival', null],
                'goods_in/putaway' => ['Putaway Queue', null]
            ], $this->permission);
            if (!empty($stock_routes) && ($this->user->hasSupplierAccess() || $this->user->hasWorkerAccess()))
                $this->addItem('Goods In', 'sign-in-alt', $stock_routes);
            $housekeeping = [
                'movement' => ['Movements', null],
               // 'processing' => ['Processing Movements', null],
                'movement/create_pallet_movement' => ['Pallet Movements', null],
                'housekeeping/pallet_merge' => ['Pallet Merge', null],
                0 => null,
                'housekeeping/inventory' => ['Inventory', null],
                'housekeeping/counting' => ['Reconciliation', null],
                1 => null,
                'batches/expired' => ['Expired (in stock)', $this->user->hasManagerAccess()],
                'batches/expired_tool' => ['Expired (goods out)', !$this->is_virtual && $this->user->hasManagerAccess()],
            ];
            if (!$this->user->isClientSide())
                $housekeeping += [
                    2 => null,
                    'housekeeping/barcode_creator' => ['Barcode Creator', !$this->is_virtual]
                ];
            $housekeeping_routes = $this->createSubMenuArray($housekeeping, $this->permission);
            if (!empty($housekeeping_routes))
                $this->addItem('Housekeeping', 'indent', $housekeeping_routes);
            if ($this->user->hasSupplierAccess() || $this->user->hasManagerAccess())
                $this
                    ->addHeading('Stock')
                    ->addItem('Orders', 'clipboard-list', 'orders');
            elseif ($this->user->isRoleWorker())
                $this->addItem('Orders (Minified)', 'clipboard-list', '', false, 'orders/minified');
        }
        // Pick&Pack
        if (!$this->is_virtual && $this->user->hasWorkerAccess()) {
            $pick_routes = $this->createSubMenuArray([
                'pick/picking_s' => ['Pick Up (single)', $this->user->isRoleSA() ?: null],
                'pick/picking_m' => ['Pick Up (multi)', $this->user->isRoleSA() ?: null],
                'pick/picking_l' => ['Pick Up (large)', $this->user->isRoleSA() ?: null],
            ], $this->permission);
            $drop_routes = $this->createSubMenuArray([
                'drop/dropping_s' => ['Drop Off (single)', $this->user->isRoleSA() ?: null],
                'drop/dropping_m' => ['Drop Off (multi)', $this->user->isRoleSA() ?: null],
                'drop/dropping_l' => ['Drop Off (large)', $this->user->isRoleSA() ?: null],
            ], $this->permission);
            $pack_routes = $this->createSubMenuArray($this->user->hasManagerAccess() ? [
                'pack/all' => ['Queue', $this->user->isRoleSA() ?: null],
                'pack/packed' => ['Orders Being Packed', $this->user->isRoleSA() ?: null],
            ] : [
                'pack/packing_s' => ['Pack (single)', null],
                'pack/packing_b' => ['Pack (bulk)', null],
                'pack/packing_m' => ['Pack (multi)', null],
                'pack/packing_l' => ['Pack (large)', null],
            ], $this->permission);
            if (!empty($pick_routes) || !empty($drop_routes) || !empty($pack_routes))
                $this
                    ->addSeparator()
                    ->addHeading('Pick&amp;Pack');
            if (!empty($pick_routes))
                $this->addItem('Pick', 'box-open', $pick_routes);
            if (!empty($drop_routes))
                $this->addItem('Drop', 'cubes', $drop_routes);
            if (!empty($pack_routes))
                $this->addItem('Pack', 'box', $pack_routes);
        }
        // Control panel
        $exist = false;
        if ($this->user->hasAllAccess()) {
            $exist = true;
            $this
                ->addSeparator()
                ->addHeading('Control Panel');
            if ($this->user->hasManagerAccess()) {
                $warehouse_routes = $this->createSubMenuArray([
                    'locations' => ['Locations', !$this->is_virtual],
                    'sizes' => ['Sizes', !$this->is_virtual],
                    'locations/binding' => ['Assignment', !$this->is_virtual],
                    'printers' => ['Printers', !$this->is_virtual],
                    'dimensioners' => ['Dimensioners', !$this->is_virtual],
                    1 => null,
                    'carriers' => ['Carriers', $this->user->isRoleSA()],
                    'return_carriers' => ['Return carriers', !$this->is_virtual && $this->user->isRoleSA()],
                    'shipping_services' => ['Services', true],
                    'collection_times' => ['Collection Time Management', !$this->is_virtual && $this->user->isRoleSA()],
                    2 => null,
                    'user_jobs/admin' => ['Queue', $this->user->isRoleSA() || $this->user->hasManagerAccess()],
                    'queues/bulk_list' => ['Packing bulk (queue)', $this->user->isRoleSA()],
                    'reports' => ['Reports', true],
                    3 => null,
                    'warehouses/settings' => ['Settings', $this->user->isRoleSA()],
                ], $this->permission);
                $this->addItem('Warehouse', 'warehouse', $warehouse_routes);
            }
            if (!$this->is_virtual && $this->user->hasWorkerAccess()) {
                $this->addItem('Trolley', 'shopping-cart', '', false, 'cart');
                if ($this->user->isRoleWorker()) {
                    if ($this->permission->isAccessByUrl('printers')) {
                        $this->addItem('Printers', 'print', '', false, 'printers');
                    }
                    $this->addItem('Dimensioners', 'vector-square', '', false, 'dimensioners');
                }
            }
            if ($this->user->hasAdminAccess() || $this->user->hasManagerAccess())
                $this->addItem('Goods', 'industry', [
                    'SKUs' => ['SKUs', true],
                    'Consumables' => ['consumables', !$this->is_virtual],
                ]);
            if ($this->user->hasAdminAccess() && !$this->user->isRoleSA()) {
                $this->addItem('Expired', 'calendar-times', [
                    'In stock' => ['batches/expired', true],
                    'Goods out' => ['batches/expired_tool', !$this->is_virtual],
                ]);
                $this->addItem('Reports', 'calendar-alt');
            }
            if ($this->user->hasAdminsAccess())
                if (!$this->is_virtual || !$this->user->hasManagerAccess() || $this->user->isRoleSA())
                    $this->addItem('Accounts', 'user', [
                        'Admins' => ['users/admins', $this->user->isRoleSA()],
                        'Companies' => ['suppliers', $this->user->hasAdminAccess()],
                        'Managers' => ['users/managers', $this->user->isRoleSA()],
                        'Workers' => ['users/workers', !$this->is_virtual && $this->user->hasManagerAccess()],
                        0 => ['-', $this->user->isRoleSA()],
//                        'Advanced' => ['accounts/advanced', $this->user->isRoleSA()],
//                        2 => ['-', $this->user->hasAdminAccess()],
                        'Channels' => ['channels', $this->user->hasAdminAccess()],
                        'Bundles' => ['bundles', $this->user->hasAdminAccess() || $this->user->hasManagerAccess()],
                        'Substitution Rules' => ['substitutions', $this->user->hasAdminAccess() || $this->user->hasManagerAccess()],
                    ]);
        }

        if ($this->user->isRoleSupplierUser()) {
            if (!$exist)
                $this
                    ->addSeparator()
                    ->addHeading('Control Panel');
            $this
                ->addItem('Account', 'user')
                ->addItem('Goods', 'industry', [
                    'SKUs' => ['SKUs', true],
                ])
                ->addItem('Barcode Creator', 'barcode')
                ->addItem('Channels', 'plug')
                ->addItem('Bundles', 'file-archive')
                ->addItem('Substitution Rules', 'exchange-alt', '', false, 'substitutions')
                ->addItem('Expired', 'calendar-times', [
                    'In stock' => ['batches/expired', true],
                    'Goods out' => ['batches/expired_tool', !$this->is_virtual],
                ]);
        }
        if ($this->user->hasSupplierAccess() || $this->user->hasManagerAccess())
            $this
                ->addSeparator()
                ->addHeading('Manuals')
                ->addItem('Integration documents', 'file-alt', '', false, 'integration_documents');
        if ($this->user->isRoleSA()) {
            $this
                ->addSeparator()
                ->addHeading('Admin tools')
                ->addItem('Inventory Sync', 'sync', [
                    'Synchronisation' => ['inventory_sync/sync', $this->user->isRoleSA()],
                    'History' => ['inventory_sync/history', $this->user->isRoleSA()],
                ])
                ->addItem('Billing', 'wallet', [
                    'Charges' => ['billing/charges', $this->user->isRoleSA()],
                    'Recurring Charges' => ['billing/recurring', $this->user->isRoleSA()],
                    'Tariff Groups' => ['billing/tariff_groups', $this->user->isRoleSA()],
                    0 => ['-', $this->user->isRoleSA()],
                    'Packagings' => ['billing/packagings', $this->user->isRoleSA()],
                    'Companies' => ['billing/companies', $this->user->isRoleSA()],
                    1 => ['-', $this->user->isRoleSA()],
                    'Invoices' => ['billing/invoices', $this->user->isRoleSA()],
                ]);
            if (Environment::isDev())
                $this
                    ->addSeparator()
                    ->addHeading('Services')
                    ->addItem('Log', 'book');
        }
    }
}