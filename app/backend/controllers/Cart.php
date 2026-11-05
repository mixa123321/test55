<?php

namespace app\backend\controllers;

use app\backend\models\Cart as CartModel;
use app\backend\models\Drop;
use app\backend\models\Locations;
use app\backend\models\Pick as PickModel;
use app\backend\models\SKULocations;
use app\backend\models\Users;
use app\core\entities\Location;
use app\core\exceptions\RedirectException;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\SectionBindings;
use app\core\helpers\Debug;

class Cart extends AuthController
{
    /** @throws */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->mobile = true;
    }

    /** @throws */
    public function index()
    {
        throw new RedirectException("/cart/set_cart/?id={$this->user->getId()}&back=/");
    }

    /** @throws */
    public function setCart()
    {
        $id = Request::getPositiveId();
        if (!$id) {
            View::addFlash('cart', 'danger', 'Invalid user');
            throw new RedirectException('/');
        }
        $user = Users::getById($id);
        $cart = CartModel::getCart($user);
        $carts = CartModel::getCarts($this->warehouse_id);
        $virtuals = CartModel::getVirtuals($this->warehouse_id, $user);
        $pallets = CartModel::getPallets($this->warehouse_id);
        $weight = SKULocations::getWeight($this->warehouse_id, array_keys($carts + $virtuals + $pallets));
        CartModel::addWeight($carts, $weight);
        CartModel::addWeight($virtuals, $weight);
        CartModel::addWeight($pallets, $weight);
        $data = [
            'cart' => $cart,
            'carts' => $carts,
            'virtuals' => $virtuals,
            'pallets' => $pallets,
            'virt_id' => Locations::getVirtualTrolley($user),
            'user' => $user,
            'mode' => '',
            'back' => empty($_GET['back']) ? '/users/workers?' . $_SESSION[Users::FILTER_PARAM] : addslashes($_GET['back']),
        ];
        $this->render($data, 'warehouse/cart');
    }

    /** @throws */
    public function change()
    {
        $mode = $_POST['mode'];
        $back = $_POST['back'];
        $id = Request::getPositiveId($_POST, 'user_id');
        if (empty($mode))
            $back_err = "/cart/set_cart/?id=$id&back=$back";
        else
            $back_err = "/cart/$mode/?back=" . str_replace('^', '&', $back);
        $cart_id = CartModel::getIndexCarts($this->warehouse_id, $_POST['cart_new'], array_diff($_POST['carts'], $_POST['busy_carts'] ?? []));
        if (!$cart_id) {
            $cart_id_near_table = SectionBindings::getCartIdByName($this->warehouse_id, $_POST['cart_new']);
            if (!empty($cart_id_near_table))
                throw new RedirectException("{$back_err}&unhook_cart_id=$cart_id_near_table");
            View::addFlash('cart', 'danger', 'Invalid location');
            throw new RedirectException($back_err);
        }
        $cart = Locations::getById($cart_id);
        if ($cart->isDetached()) {
            if ($this->user->isRoleWorker()) {
                View::addFlash('cart', 'danger', 'You cannot take pallet. Contant manager');
                throw new RedirectException($back_err);
            }
            $user = Users::getById($id);
            if (!$user->isRoleWorker()) {
                View::addFlash('cart', 'danger', 'Only worker can take pallet');
                throw new RedirectException($back_err);
            }
            if (!empty(SectionBindings::getBindingType($cart_id))) {
                $orders = CartModel::getOrderIdsInTrolley($cart_id);
                if (!empty($orders)) {
                    View::addFlash('cart', 'danger', 'You cannot take pallet from table while packing');
                    throw new RedirectException($back_err);
                }
            }
        }

        if (empty($mode) || in_array($mode, CartModel::$modes)) {

            if ($cart->isLocked()) {
                View::addFlash('cart', 'danger', 'Cart is locked and cannot be taken');
                throw new RedirectException($back_err);
            }

            CartModel::takeForceCart($id, $cart_id);

            throw new RedirectException(empty($mode) ? $back_err : str_replace('^', '?', $back));
        }

        throw new RedirectException(str_replace('^', '?', $back));
    }

    /** @throws */
    public function delete()
    {
        $id = Request::getPositiveId();
        if ($this->user->isRoleWorker() && $id != $this->user->getId()) {
            View::addFlash('cart', 'danger', 'You cannot release another user\'s trolley');
            throw new RedirectException('/cart/set_cart');
        }
        $mode = addslashes($_GET['mode']);
        $back = addslashes($_GET['back']);
        $cart = CartModel::getCart(Users::getById($id));
        if (empty($cart)) {
            View::addFlash('cart', 'danger', 'Cart not found');
            throw new RedirectException($back);
        }
        CartModel::freeCart($id);
        throw new RedirectException('/cart/' .
            (empty($mode) ? "set_cart/?id=$id&back=$back"
                          : ("$mode/?back=" . str_replace('^', '&', $back))));
    }

    /** @throws */
    public function hands()
    {
        $mode = $_GET['mode'];
        $back = $_GET['back'];
        $id = Request::getPositiveId();
        if (!$id) {
            View::addFlash('cart', 'danger', 'Invalid user');
            throw new RedirectException('/');
        }
        if (empty($mode))
            $back_err = "/cart/set_cart/?id=$id&back=$back";
        else
            $back_err = "/cart/$mode/?back=" . str_replace('^', '&', $back);
        $user = Users::getById($id);
        $cart = CartModel::getCart($user);
        if ($cart->isDetached()) {
            View::addFlash('cart', 'danger', 'You cannot use pallet for this operation');
            throw new RedirectException($back_err);
        }
        $virt_id = Locations::getVirtualTrolley($user);
        list($loc_from_id, $mode_from) = explode('_', $_GET['loc_from_id']);
        $modes = explode(',', $mode_from);
        $loc_from_id = (int) $loc_from_id;
        $loc_to_id = (int) $_GET['loc_to_id'];
        if (empty($user)) {
            View::addFlash('cart', 'danger', 'User not found');
            throw new RedirectException($back_err);
        }
        if (empty($loc_from_id) || ($cart->getId() != $loc_from_id && $virt_id != $loc_from_id) || empty($modes)) {
            View::addFlash('cart', 'danger', 'Invalid source location');
            throw new RedirectException($back_err);
        }
        if (empty($loc_to_id) || ($cart->getId() != $loc_to_id && $virt_id != $loc_to_id)) {
            View::addFlash('cart', 'danger', 'Invalid destination location');
            throw new RedirectException($back_err);
        }
        CartModel::changeTrolleys($loc_from_id, $loc_to_id, $modes, $user, $this->warehouse_id);
        $carts = CartModel::getCarts($this->warehouse_id);
        $virtuals = CartModel::getVirtuals($this->warehouse_id, $user);
        $pallets = CartModel::getPallets($this->warehouse_id);
        $weight = SKULocations::getWeight($this->warehouse_id, array_keys($carts + $virtuals + $pallets));
        CartModel::addWeight($carts, $weight);
        CartModel::addWeight($virtuals, $weight);
        CartModel::addWeight($pallets, $weight);
        $data = [
            'cart' => $cart,
            'carts' => $carts,
            'virtuals' => $virtuals,
            'pallets' => $pallets,
            'virt_id' => Locations::getVirtualTrolley($user),
            'user' => $user,
            'mode' => $mode,
            'back' => $back,
        ];
        $this->render($data, 'warehouse/cart');
    }

    /** @throws */
    private function control($mode, $back)
    {
        $carts = CartModel::getCarts($this->warehouse_id);
        $virtuals = CartModel::getVirtuals($this->warehouse_id, $this->user);
        $pallets = CartModel::getPallets($this->warehouse_id);
        $weight = SKULocations::getWeight($this->warehouse_id, array_keys($carts + $virtuals + $pallets));
        CartModel::addWeight($carts, $weight);
        CartModel::addWeight($virtuals, $weight);
        CartModel::addWeight($pallets, $weight);
        if (in_array($mode, CartModel::$modes))
            $cart = CartModel::getCart($this->user);
        else {
            View::addFlash('cart', 'danger', 'Unexpected mode');
            throw new RedirectException('/');
        }
        $data = [
            'cart' => $cart,
            'carts' => $carts,
            'pallets' => $pallets,
            'virtuals' => $virtuals,
            'virt_id' => Locations::getVirtualTrolley($this->user),
            'user' => $this->user,
            'mode' => $mode,
            'back' => $back,
        ];
        $this->render($data, 'warehouse/cart');
    }

    /** @throws */
    public function picking()
    {
        $this->control('picking', $_GET['back']);
    }

    /** @throws */
    public function arrival()
    {
        $back = "{$_GET['back']}^id={$_GET['id']}&sku=" . urlencode($_GET['sku']);
        $this->control('arrival', $back);
    }

    /** @throws */
    public function putaway()
    {
        $back = "{$_GET['back']}^SKU_id={$_GET['SKU_id']}&location_id={$_GET['location_id']}&qty={$_GET['qty']}";
        $this->control('putaway', $back);
    }

    /** @throws */
    public function unexpected()
    {
        $back = "{$_GET['back']}^id={$_GET['id']}&p={$_GET['p']}" . (!empty($_GET['s']) ? "&s={$_GET['s']}" : '') . (!empty($_GET['c']) ? "&c={$_GET['c']}" : '');
        $this->control('unexpected', $back);
    }

    /** @throws */
    public function unexpectedNoASN()
    {
        $back = "{$_GET['back']}^p={$_GET['p']}" . (!empty($_GET['s']) ? "&s={$_GET['s']}" : '') . (!empty($_GET['c']) ? "&c={$_GET['c']}" : '');
        $this->control('unexpected_noASN', $back);
    }

    /** @throws */
    public function dropping()
    {
        $this->control('dropping', $_GET['back']);
    }

    /** @throws */
    public function movement()
    {
        $this->control('movement', $_GET['back']);
    }

    /** @throws */
    public function unhookTrolley()
    {
        $back = $_GET['back'];
        $cart_id = Request::getPositiveId(null, 'cart_id');
        $location = Locations::getById($cart_id);
        if (!$location) {
            View::addFlash('unhookTrolley', 'danger', 'Location not found');
            throw new RedirectException($back);
        }

        if ($this->user->isRoleWorker() || $this->user->hasWorkersAccess()) {
            if ($this->user->getCartId() != null) {
                View::addFlash('unhookTrolley', 'danger', 'You cannot unhook trolley, when you have another one');
                throw new RedirectException($back);
            }

            $unhook = CartModel::unhookTrolleyFromPackingTable($this->user, $location->getId(), $this->warehouse_id);

            if (empty($unhook[0])) {
                View::addFlash('unhookTrolley', 'danger', $unhook[1]);
                throw new RedirectException($back);
            }

            if ($location->isDetached()) {
                $info = CartModel::getCartInfo($location);
                foreach ($info as $mode => $_data) {
                    switch ($mode) {
                        case CartModel::MODE_LARGE:
                        case CartModel::MODE_MULTI:
                        case CartModel::MODE_SINGLE:
                            throw new RedirectException(Drop::getDroppingUrlByMode($mode));
                    }
                }
                View::addFlash('unhookTrolley', 'warning', 'Please return pallet');
                throw new RedirectException('/cart/return_pallet');
            }

            View::addFlash('unhookTrolley', 'success', 'Trolley was unhooked successfully');
        }

        throw new RedirectException($back);
    }

    /** @throws */
    public function leaveTrolley()
    {
        //TODO метод leaveTrolley скорее всего не используеться
        Debug::sendDebugInfo('Use method leaveTrolley', "", Debug::TEST);

        $cartId = (int)$_REQUEST['cart_id'];
        $mode = $_REQUEST['mode'];
        $tableId = (int)$_REQUEST['table_id'];
        $back = $_REQUEST['back'];
        if (!in_array($mode, ['single', 'large'])) {
            View::addFlash('dropping', 'danger', 'You cannot leave this trolley');
            throw new RedirectException($back);
        }
        $cart = Locations::getById($cartId);
        if (!$cart) {
            View::addFlash('leaveTrolley', 'danger', 'Trolley not found');
            throw new RedirectException($back);
        }
        $table = Locations::getById($tableId);
        if (!$table) {
            View::addFlash('leaveTrolley', 'danger', 'Table not found');
            throw new RedirectException($back);
        }
        CartModel::freeCart($this->user->getId());
        PickModel::leaveTrolleyNearTable($cartId, $mode, $tableId);
        View::addFlash('leaveTrolley', 'success', 'Trolley successfully leaved near table');
        throw new RedirectException($back);
    }

    public function returnPallet()
    {
        $back = Request::get('back') ?: '/cart/set_cart?id=' . $this->user->getId() . '&back=/';
        $cart = CartModel::getCart($this->user);
        if (!$cart || !$cart->isDetached()) {
            View::addFlash('cart', 'danger', 'Pallet not found');
            throw new RedirectException($back);
        }
        if (Request::isAjax()) {
            if (empty($_POST['scan']))
                return $this->jsonResponse(['success' => false, 'message' => 'Scan is empty']);
            $location = Locations::getByScan($this->warehouse_id, $_POST['scan']);
            if (!$location || $location->getId() != $cart->getId())
                return $this->jsonResponse(['success' => false, 'message' => 'Invalid location']);
            $info = CartModel::getCartInfo($cart);
            if (!empty(array_diff_key($info, [CartModel::MODE_NONE => 0, CartModel::MODE_MOVE => 0]))) {
                $res = PickModel::unpickSKUsInLoc($this->warehouse, $this->user, $cart);
                if (!empty($res['errors']))
                    return $this->jsonResponse(['success' => false, 'message' => implode(', ', $res['errors'])]);
                View::addFlash('cart', 'success', $res['successes']);
            }
            $cart->setState(Location::ACTIVE);
            $cart->save(['id', 'state']);
            CartModel::freeCart($this->user->getId());
            View::addFlash('cart', 'success', 'Pallet was returned successfully');
            return $this->jsonResponse(['success' => true]);
        }
        $SKUs = SKULocations::getSKUsFromLocation($cart->getId());
        return $this->render([
            'back' => $back,
            'cart' => $cart,
            'warehouse_label' => $this->warehouse_label,
            'scan' => Request::get('scan'),
            'SKUs' => $SKUs,
        ], 'warehouse/return_pallet');
    }
}
