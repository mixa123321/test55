<?php

namespace app\core\entities;

use app\backend\models\Carriers;
use app\backend\models\Deliveries;
use app\backend\models\OrderPackages;
use app\backend\models\Suppliers;
use app\backend\models\Users;
use app\core\db\Mysql;
use app\core\models\OrderCategories;
use app\core\models\Shippings;
use app\core\models\ShippingServices;
use app\core\models\TokensTranslations;
use app\core\statical\Sources;
use app\core\statical\WithDate;

class Order extends Entity
{
    const SCHEDULED_NUM                         = -16;
    const ERROR_VIRTUAL_NUM                     = -15;
    const AWAITING_UPLOAD_DOCUMENTS_NUM         = -142;
    const AWAITING_CHANNEL_SERVICE_MAPPING_NUM  = -14;
    const ERROR_NUM                             = -13;
    const PAUSED_NUM                            = -12;
    const QUERY_NUM                             = -11;
    const CANCELLED_NUM                         = -3;
    const NEW_NUM                               =  11;
    const ALLOCATING_STOCK_NUM                  =  12;
    const BACKORDER_NUM                         =  14;
    const PENDING_APPROVAL_NUM                  =  151;
    const APPROVED_NUM                          =  159;
    const EXPIRED_NUM                           =  16;
    //const EXPIRED_APPROVAL_NUM                  =  161;
    const ALLOCATED_NUM                         =  19;
    const WAITING_FOR_REPLEN_NUM                =  310;
    //const AWAITING_LABEL_NUM                    =  311;
    //const AWAITING_PACKING_SLIP_NUM             =  312;
    const WAITING_FOR_PICK_NUM                  =  32;
    const WAITING_FOR_LARGE_NUM                 =  3210;
    const AWAITING_LARGE_PICK_NUM               =  3211;
    const LARGE_PICKING_NUM                     =  3219;
    const WAITING_FOR_SINGLE_NUM                =  3220;
    const AWAITING_SINGLE_PICK_NUM              =  3221;
    const SINGLE_PICKING_NUM                    =  3229;
    const WAITING_FOR_ORDER_BIN_NUM             =  3240;
    const AWAITING_PICK_NUM                     =  3241;
    const PICKING_NUM                           =  3249;
    //const WAITING_FOR_BULK_NUM                  =  3250;
    const PROCESSING_NUM                        =  329;
    const AWAITING_PACK_NUM                     =  39;
    const PACKING_NUM                           =  41;
    const PACKING_ISSUE_NUM                     =  410;
    const PRINTING_DOCUMENTS_NUM                =  45;
    const PRINTING_DOCUMENTS_ISSUE_NUM          =  450;
    const PACKED_NUM                            =  49;
    //const WHOLESALE_AWAITING_APPROVAL_NUM       =  501;
    //const CROSSDOCK_AWAITING_APPROVAL_NUM       =  502;
    //const AMAZON_FBA_AWAITING_APPROVAL_NUM      =  503;
    const DESPATCHED_NUM                        =  59;
    const RETURNED_NUM                          =  69;

    const STAGE_NONE                            = -1;
    const STAGE_CREATED                         =  0;
    const STAGE_ALLOCATING_STOCK                =  1;
    const STAGE_PICKING                         =  3;
    const STAGE_PACKING                         =  4;
    const STAGE_DESPATCHED                      =  5;

    const SCHEDULED                             = 'scheduled';
    const ERROR_VIRTUAL                         = 'error (virtual)';
    const AWAITING_UPLOAD_DOCUMENTS             = 'awaiting upload documents';
    const AWAITING_CHANNEL_SERVICE_MAPPING      = 'awaiting channel service mapping';
    const ERROR                                 = 'error';
    const PAUSED                                = 'paused';
    const QUERY                                 = 'query';
    const CANCELLED                             = 'cancelled';
    const NEW                                   = 'new';
    const ALLOCATING_STOCK                      = 'allocating stock';
    const BACKORDER                             = 'backorder';
    const PENDING_APPROVAL                      = 'pending approval';
    const APPROVED                              = 'approved';
    const EXPIRED                               = 'expired';
    const ALLOCATED                             = 'allocated';
    const WAITING_FOR_REPLEN                    = 'waiting for replen';
    const WAITING_FOR_LARGE                     = 'waiting for large';
    const AWAITING_LARGE_PICK                   = 'awaiting large pick';
    const LARGE_PICKING                         = 'large picking';
    const WAITING_FOR_SINGLE                    = 'waiting for single';
    const AWAITING_SINGLE_PICK                  = 'awaiting single pick';
    const SINGLE_PICKING                        = 'single picking';
    const WAITING_FOR_ORDER_BIN                 = 'waiting for order bin';
    const AWAITING_PICK                         = 'awaiting pick';
    const PICKING                               = 'picking';
    const PROCESSING                            = 'processing';
    const AWAITING_PACK                         = 'awaiting pack';
    const PACKING                               = 'packing';
    const PACKING_ISSUE                         = 'packing issue';
    const PRINTING_DOCUMENTS                    = 'printing documents';
    const PRINTING_DOCUMENTS_ISSUE              = 'printing documents issue';
    const PACKED                                = 'packed';
    const DESPATCHED                            = 'despatched';
    const RETURNED                              = 'returned';

    private $id;
    private $delivery_id;
    private $carrier_id;
    private $despatch_date;
    private $despatched_date;
    private $ref;
    private $picking;
    private $packing;
    private $notes;
    private $create_date;
    private $user_id;
    private $status = self::NEW_NUM;
    private $billing_description_preparation;
    private $billing_charge_preparation;
    private $billing_description_shipping;
    private $billing_charge_shipping;
    private $billing_description_additional;
    private $billing_charge_additional;
    private $billing_charge_currency;
    private $supplier_id;
    private $shipping_services_id = 0;
    private $channels_shipping_services_id = 0;
    private $ref2;
    private $external_id;
    private $type_create;
    private $priority;
    private $packing_slip_print = 0;
    private $warehouse_id;
    private $shop_id = 0;
    private $DDP;
    private $currency;
    private $category = OrderCategories::DEFAULT;
    private $is_hash = false;
    private $purchase_order_number;

    public static $allowed_update_statuses = [
        self::NEW_NUM,
        self::ALLOCATING_STOCK_NUM,
        self::BACKORDER_NUM,
        self::APPROVED_NUM,
        self::ALLOCATED_NUM,
        self::WAITING_FOR_REPLEN_NUM,
    ];

    public static $allowed_for_printing_statutes = [
        self::PRINTING_DOCUMENTS_NUM,
        self::PRINTING_DOCUMENTS_ISSUE_NUM
    ];

    public static $allowed_for_packed_statutes = [
        self::PACKING_NUM,
        self::PACKING_ISSUE_NUM,
        self::PRINTING_DOCUMENTS_NUM,
        self::PRINTING_DOCUMENTS_ISSUE_NUM
    ];

    public static $allowed_for_packing_slip_statutes = [
        self::PRINTING_DOCUMENTS_NUM,
        self::PRINTING_DOCUMENTS_ISSUE_NUM,
        self::PACKED_NUM,
        self::DESPATCHED_NUM,
        self::RETURNED_NUM
    ];

    public static $can_query = [
        self::BACKORDER_NUM,
        self::PENDING_APPROVAL_NUM,
        self::WAITING_FOR_REPLEN_NUM,
        self::AWAITING_PICK_NUM,
        self::AWAITING_SINGLE_PICK_NUM,
        self::AWAITING_LARGE_PICK_NUM,
        self::PICKING_NUM,
        self::SINGLE_PICKING_NUM,
        self::LARGE_PICKING_NUM,
        self::PACKING_NUM,
        self::AWAITING_PACK_NUM,
    ];

    /* EORI, VAT, IOSS, TAX ID: editable up to packing stage before the shipping label is created */
    public static $can_edit_tax_numbers = [
        self::CANCELLED_NUM,
        self::QUERY_NUM,
        self::PAUSED_NUM,
        self::AWAITING_PACK_NUM,
        self::PACKING_NUM,
        self::PACKING_ISSUE_NUM,
        self::PRINTING_DOCUMENTS_ISSUE_NUM,
    ];

    public static $statusName = [
        self::SCHEDULED_NUM => self::SCHEDULED,
        self::ERROR_VIRTUAL_NUM => self::ERROR_VIRTUAL,
        self::AWAITING_UPLOAD_DOCUMENTS_NUM => self::AWAITING_UPLOAD_DOCUMENTS,
        self::AWAITING_CHANNEL_SERVICE_MAPPING_NUM => self::AWAITING_CHANNEL_SERVICE_MAPPING,
        self::ERROR_NUM => self::ERROR,
        self::PAUSED_NUM => self::PAUSED,
        self::QUERY_NUM => self::QUERY,
        self::CANCELLED_NUM => self::CANCELLED,
        //-2 => 'held billing',
        self::NEW_NUM => self::NEW,
        self::ALLOCATING_STOCK_NUM => self::ALLOCATING_STOCK,
        self::BACKORDER_NUM => self::BACKORDER,
        self::PENDING_APPROVAL_NUM => self::PENDING_APPROVAL,
        self::APPROVED_NUM => self::APPROVED,
        self::EXPIRED_NUM => self::EXPIRED,
        self::ALLOCATED_NUM => self::ALLOCATED,
        self::WAITING_FOR_REPLEN_NUM => self::WAITING_FOR_REPLEN,
        // large
        self::WAITING_FOR_LARGE_NUM => self::WAITING_FOR_LARGE,
        self::AWAITING_LARGE_PICK_NUM => self::AWAITING_LARGE_PICK,
        self::LARGE_PICKING_NUM => self::LARGE_PICKING,
        // single
        self::WAITING_FOR_SINGLE_NUM => self::WAITING_FOR_SINGLE,
        self::AWAITING_SINGLE_PICK_NUM => self::AWAITING_SINGLE_PICK,
        self::SINGLE_PICKING_NUM => self::SINGLE_PICKING,
        // wholesale
//        3230 => 'waiting for wholesale',
//        3231 => 'awaiting wholesale pick',
//        3239 => 'wholesale picking',
        // multi
        self::WAITING_FOR_ORDER_BIN_NUM => self::WAITING_FOR_ORDER_BIN,
        self::AWAITING_PICK_NUM => self::AWAITING_PICK,
        self::PICKING_NUM => self::PICKING,
        self::PROCESSING_NUM => self::PROCESSING,
        self::AWAITING_PACK_NUM => self::AWAITING_PACK,
        self::PACKING_NUM => self::PACKING,
        self::PACKING_ISSUE_NUM => self::PACKING_ISSUE,
        self::PRINTING_DOCUMENTS_NUM => self::PRINTING_DOCUMENTS,
        self::PRINTING_DOCUMENTS_ISSUE_NUM => self::PRINTING_DOCUMENTS_ISSUE,
        self::PACKED_NUM => self::PACKED,
        self::DESPATCHED_NUM => self::DESPATCHED,
        self::RETURNED_NUM => self::RETURNED,
    ];

    private static $statusStage = [
        self::STAGE_CREATED => 'Created',
        self::STAGE_ALLOCATING_STOCK => 'Allocating Stock',
        self::STAGE_PICKING => 'Picking',
        self::STAGE_PACKING => 'Packing',
        self::STAGE_DESPATCHED => 'Despatched',
    ];

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getDeliveryId()
    {
        return $this->delivery_id;
    }

    public function setDeliveryId($id)
    {
        $this->delivery_id = (int)$id;
    }

    public function getCarrierId()
    {
        return $this->carrier_id;
    }

    public function setCarrierId($id)
    {
        $this->carrier_id = (int)$id;
    }

    public function getDespatchDate()
    {
        return empty($this->despatch_date) ? '' : date('Y-m-d', $this->despatch_date);
    }

    public function getDespatchDateInt()
    {
        return $this->despatch_date;
    }

    public function setDespatchDate($despatch_date)
    {
        $this->despatch_date = WithDate::dateToInt($despatch_date);
    }

    public function getDespatchedDate()
    {
        return empty($this->despatched_date) ? '' : date(WithDate::DATE_SQL_FORMAT, $this->despatched_date);
    }

    public function getDespatchedDateInt()
    {
        return $this->despatched_date;
    }

    public function setDespatchedDate($despatched_date)
    {
        $this->despatched_date = WithDate::dateToInt($despatched_date);
    }

    public function getRef()
    {
        return $this->ref;
    }

    public function setRef($ref)
    {
        $this->ref = (string)$ref;
    }

    public function getPicking()
    {
        return $this->picking;
    }

    public function setPicking($info)
    {
        $this->picking = (string)$info;
    }

    public function getPacking()
    {
        return $this->packing;
    }

    public function setPacking($info)
    {
        $this->packing = (string)$info;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function setNotes($info)
    {
        $this->notes = (string)$info;
    }

    public function getCreateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    public function getCreateDateInt()
    {
        return $this->create_date;
    }

    public function setCreateDate($create_date)
    {
        $this->create_date = WithDate::dateToInt($create_date);
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id)
    {
        $this->user_id = (int)$id;
    }

    public function getShopId()
    {
        return $this->shop_id;
    }

    public function setShopId($id)
    {
        $this->shop_id = (int)$id;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = (int)$status;
    }

    public static function getStatusFromName($name)
    {
        return array_search($name, self::$statusName);
    }

    public function setStatusName($name)
    {
        $pos = self::getStatusFromName($name);
        if ($pos === false)
            return;
        $this->setStatus($pos);
    }

    public function getStatusName()
    {
        return self::getStateName($this->status);
    }

    public static function getStateName($status, $tokens = [])
    {
        return mb_strtolower(TokensTranslations::getTranslate($tokens, self::$statusName[$status] ?? 'not found'));
    }

    public static function colorStatus($status)
    {
        if (self::isCancelled($status) || self::isBackorder($status) || self::isQuery($status))
            $badge_style = 'secondary';
        elseif (self::isAwaitingChannelServiceMapping($status) || self::isScheduled($status) || self::isPendingApproval($status))
            $badge_style = 'warning';
        elseif ($status < self::NEW_NUM || in_array($status, [self::PACKING_ISSUE_NUM, self::PRINTING_DOCUMENTS_ISSUE_NUM, self::EXPIRED_NUM]))
            $badge_style = 'danger';
        elseif (substr($status, 0, 2) == 31)
            $badge_style = 'warning';
        elseif (self::isDespatched($status))
            $badge_style = 'success';
        else
            $badge_style = 'primary';
        return $badge_style;
    }

    /** is.. - static */
    public static function isScheduled($status)
    {
        return $status == self::SCHEDULED_NUM;
    }
    // scheduled

    // isErrorVirtual
    public function errorVirtual()
    {
        return $this->getStatus() == self::ERROR_VIRTUAL_NUM;
    }

    public static function isAwaitingChannelServiceMapping($status)
    {
        return $status == self::AWAITING_CHANNEL_SERVICE_MAPPING_NUM;
    }

    public function awaitingChannelServiceMapping()
    {
        return self::isAwaitingChannelServiceMapping($this->getStatus());
    }

    public static function isAwaitingUploadDocuments($status): bool
    {
        return $status == self::AWAITING_UPLOAD_DOCUMENTS_NUM;
    }

    public function awaitingUploadDocuments(): bool
    {
        return self::isAwaitingUploadDocuments($this->getStatus());
    }

    // isError
    public function error()
    {
        return $this->getStatus() == self::ERROR_NUM;
    }

    public static function isPaused($status)
    {
        return $status == self::PAUSED_NUM;
    }

    public function paused()
    {
        return self::isPaused($this->getStatus());
    }

    public static function isQuery($status)
    {
        return $status == self::QUERY_NUM;
    }

    public function query()
    {
        return self::isQuery($this->getStatus());
    }

    public static function isCancelled($status)
    {
        return $status == self::CANCELLED_NUM;
    }

    public function cancelled()
    {
        return self::isCancelled($this->getStatus());
    }

    public static function isNew($status)
    {
        return $status == self::NEW_NUM;
    }

    public function new()
    {
        return self::isNew($this->getStatus());
    }

    // isAllocatingStock
    public function allocatingStock()
    {
        return $this->getStatus() == self::ALLOCATING_STOCK_NUM;
    }

    public static function isBackorder($status)
    {
        return $status == self::BACKORDER_NUM;
    }

    public function backorder()
    {
        return self::isBackorder($this->getStatus());
    }

    public static function isPendingApproval($status)
    {
        return $status == self::PENDING_APPROVAL_NUM;
    }

    public function pendingApproval()
    {
        return self::isPendingApproval($this->getStatus());
    }

    // isApproved
    public function approved()
    {
        return $this->getStatus() == self::APPROVED_NUM;
    }

    public static function isExpired($status)
    {
        return $status == self::EXPIRED_NUM;
    }

    public function expired()
    {
        return self::isExpired($this->getStatus());
    }

    public static function isAllocated($status)
    {
        return $status == self::ALLOCATED_NUM;
    }

    public function allocated()
    {
        return self::isAllocated($this->getStatus());
    }

    public static function isWaitingForReplen($status)
    {
        return $status == self::WAITING_FOR_REPLEN_NUM;
    }

    public function waitingForReplen()
    {
        return self::isWaitingForReplen($this->getStatus());
    }

    // isWaitingForLarge
    public function waitingForLarge()
    {
        return $this->getStatus() == self::WAITING_FOR_LARGE_NUM;
    }

    // isWaitingForSingle
    public function waitingForSingle()
    {
        return $this->getStatus() == self::WAITING_FOR_SINGLE_NUM;
    }

    public static function isWaitingForOrderBin($status)
    {
        return $status == self::WAITING_FOR_ORDER_BIN_NUM;
    }

    public function waitingForOrderBin()
    {
        return self::isWaitingForOrderBin($this->getStatus());
    }

    public static function isAwaitingPick($status)
    {
        return $status == self::AWAITING_PICK_NUM;
    }

    public static function isAwaitingSinglePick($status)
    {
        return $status == self::AWAITING_SINGLE_PICK_NUM;
    }

    public static function isAwaitingLargePick($status)
    {
        return $status == self::AWAITING_LARGE_PICK_NUM;
    }

    public static function isPicking($status)
    {
        return $status == self::PICKING_NUM;
    }

    public static function isSinglePicking($status)
    {
        return $status == self::SINGLE_PICKING_NUM;
    }

    public static function isLargePicking($status)
    {
        return $status == self::LARGE_PICKING_NUM;
    }

    public static function isAwaitingPack($status)
    {
        return $status == self::AWAITING_PACK_NUM;
    }

    public function awaitingPack()
    {
        return self::isAwaitingPack($this->getStatus());
    }

    public static function isPacking($status)
    {
        return $status == self::PACKING_NUM;
    }

    public function packing()
    {
        return self::isPacking($this->getStatus());
    }

    public static function isPrintingDocuments($status)
    {
        return $status == self::PRINTING_DOCUMENTS_NUM;
    }

    public function printingDocuments()
    {
        return self::isPrintingDocuments($this->getStatus());
    }

    public static function isPrintingDocumentsIssue($status)
    {
        return $status == self::PRINTING_DOCUMENTS_ISSUE_NUM;
    }

    public function printingDocumentsIssue()
    {
        return self::isPrintingDocumentsIssue($this->getStatus());
    }

    public static function isPacked($status)
    {
        return $status == self::PACKED_NUM;
    }

    public function packed()
    {
        return self::isPacked($this->getStatus());
    }

    public static function isPackProcess($status)
    {
        return self::isPacking($status) || self::isPacked($status);
    }

    public static function isDespatched($status)
    {
        return $status == self::DESPATCHED_NUM;
    }

    public function despatched()
    {
        return self::isDespatched($this->getStatus());
    }
    /** ------ */

    public static function canCancelOrder($status, $warehouse_virtual, $client = false)
    {
        $cancel = $warehouse_virtual ? self::canCancelOrderVirtual($status) : ($status <= self::ERROR_NUM || ($status >= self::NEW_NUM && substr($status, 0, 2) < self::PACKING_NUM));
        return $cancel || (self::isPrintingDocumentsIssue($status) && !$client);
    }

    public static function canCancelOrderVirtual($status)
    {
        return $status <= self::PAUSED_NUM || ($status >= self::NEW_NUM && substr($status, 0, 2) <= self::ALLOCATED_NUM);
    }

    public function canEditPurchaseOrderNumber()
    {
        return $this->despatched();
    }

    public function canEdit()
    {
        return ($this->cancelled() || $this->query() || $this->paused() || $this->printingDocumentsIssue()) && !$this->getIsHash();
    }

    /* Delivery address for SA/Manager at packing stage, in addition to canEdit() */
    public function canEditDeliveryAtPacking()
    {
        return ($this->awaitingPack() || $this->packing()) && !$this->getIsHash();
    }

    public function canEditTaxNumbers()
    {
        return in_array($this->getStatus(), self::$can_edit_tax_numbers) && !$this->getIsHash();
    }

    public static function getStages()
    {
        return self::$statusStage;
    }

    public static function canQuery($status)
    {
        return in_array($status, self::$can_query);
    }

    public static function getStageNum($status)
    {
        if ($status < self::NEW_NUM)
            return self::STAGE_NONE;
        if (self::isNew($status))
            return self::STAGE_CREATED;
        $mode = substr($status, 0, 2);
        if ($mode < self::WAITING_FOR_PICK_NUM)
            return self::STAGE_ALLOCATING_STOCK;
        if ($mode == self::WAITING_FOR_PICK_NUM)
            return self::STAGE_PICKING;
        if ($mode < self::DESPATCHED_NUM)
            return self::STAGE_PACKING;
        return self::STAGE_DESPATCHED;
    }

    public function getStage()
    {
        return self::getStageNum($this->getStatus());
    }

    public function getBillingDescriptionPreparation()
    {
        return $this->billing_description_preparation;
    }

    public function setBillingDescriptionPreparation($txt)
    {
        $this->billing_description_preparation = (string)$txt;
    }

    public function getBillingChargePreparation()
    {
        return $this->billing_charge_preparation;
    }

    public function setBillingChargePreparation($sum)
    {
        $this->billing_charge_preparation = (float)$sum;
    }

    public function getBillingDescriptionShipping()
    {
        return $this->billing_description_shipping;
    }

    public function setBillingDescriptionShipping($txt)
    {
        $this->billing_description_shipping = (string)$txt;
    }

    public function getBillingChargeShipping()
    {
        return $this->billing_charge_shipping;
    }

    public function setBillingChargeShipping($sum)
    {
        $this->billing_charge_shipping = (float)$sum;
    }

    public function getBillingDescriptionAdditional()
    {
        return $this->billing_description_additional;
    }

    public function setBillingDescriptionAdditional($txt)
    {
        $this->billing_description_additional = (string)$txt;
    }

    public function getBillingChargeAdditional()
    {
        return $this->billing_charge_additional;
    }

    public function setBillingChargeAdditional($sum)
    {
        $this->billing_charge_additional = (float)$sum;
    }

    public function getBillingChargeCurrency()
    {
        return $this->billing_charge_currency;
    }

    public function setBillingChargeCurrency($txt)
    {
        $this->billing_charge_currency = (string)$txt;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($id)
    {
        $this->supplier_id = (int)$id;
    }

    public function getShippingServicesId()
    {
        return $this->shipping_services_id;
    }

    public function setShippingServicesId($shipping_services_id)
    {
        $this->shipping_services_id = (int)$shipping_services_id;
    }

    public function getChannelsShippingServicesId()
    {
        return $this->channels_shipping_services_id;
    }

    public function setChannelsShippingServicesId($channels_shipping_services_id)
    {
        $this->channels_shipping_services_id = (int)$channels_shipping_services_id;
    }

    public function getRef2()
    {
        return $this->ref2;
    }

    public function setRef2($ref2)
    {
        $this->ref2 = (string)$ref2;
    }

    public function getExternalId()
    {
        return $this->external_id;
    }

    public function setExternalId($external_id)
    {
        $this->external_id = $external_id;
    }

    public function getTypeCreate()
    {
        return $this->type_create;
    }

    public function setTypeCreate($txt)
    {
        $this->type_create = (string)$txt;
    }

    public static function isShopify($type_create)
    {
        return $type_create === Sources::SOURCE_SHOPIFY;
    }

    public static function isWooCommerce($type_create)
    {
        return $type_create === Sources::SOURCE_WOOCOMMERCE;
    }

    public static function isAPI($type_create)
    {
        return $type_create === Sources::SOURCE_API;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function setPriority($priority = null)
    {
        $this->priority = is_null($priority) ? time() : $priority;
    }

    public function isPriority()
    {
        return $this->priority > 0;
    }

    public static function canBePriority($status, $isPriority = false)
    {
        return self::isWaitingForOrderBin($status) && !$isPriority;
    }

    public function getPackingSlipPrint()
    {
        return $this->packing_slip_print;
    }

    public function setPackingSlipPrint($num)
    {
        $this->packing_slip_print = (int)$num;
    }

    public function addPackingSlipPrint()
    {
        if (empty($this->packing_slip_print))
            $this->packing_slip_print = 1;
        else
            $this->packing_slip_print++;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getDDP()
    {
        return $this->DDP;
    }

    public function setDDP($txt)
    {
        $this->DDP = (string)$txt;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setCurrency($txt)
    {
        $this->currency = $txt;
    }

    public function getPurchaseOrderNumber()
    {
        return $this->purchase_order_number;
    }

    public function setPurchaseOrderNumber($purchase_order_number)
    {
        $this->purchase_order_number = $purchase_order_number;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory($category)
    {
        $this->category = $category;
    }

    public function getIsHash()
    {
        return $this->is_hash;
    }

    public function setIsHash($hash)
    {
        $this->is_hash = (bool)$hash;
    }

    public function isHashed(): bool
    {
        return !empty($this->is_hash);
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('orders', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'delivery_id' => $this->getDeliveryId(),
            'carrier_id' => $this->getCarrierId(),
            'despatch_date' => $this->getDespatchDate(),
            'despatched_date' => $this->getDespatchedDate(),
            'ref' => $this->getRef(),
            'ref2' => $this->getRef2(),
            'picking' => $this->getPicking(),
            'packing' => $this->getPacking(),
            'notes' => $this->getNotes(),
            'create_date' => $this->getCreateDate(),
            'user_id' => $this->getUserId(),
            'shop_id' => $this->getShopId(),
            'status' => $this->getStatus(),
            'billing_description_preparation' => $this->getBillingDescriptionPreparation(),
            'billing_charge_preparation' => $this->getBillingChargePreparation(),
            'billing_description_shipping' => $this->getBillingDescriptionShipping(),
            'billing_charge_shipping' => $this->getBillingChargeShipping(),
            'billing_description_additional' => $this->getBillingDescriptionAdditional(),
            'billing_charge_additional' => $this->getBillingChargeAdditional(),
            'billing_charge_currency' => $this->getBillingChargeCurrency(),
            'supplier_id' => $this->getSupplierId(),
            'shipping_services_id' => $this->getShippingServicesId(),
            'channels_shipping_services_id' => $this->getChannelsShippingServicesId(),
            'external_id' => $this->getExternalId(),
            'priority' => $this->getPriority(),
            'type_create' => $this->getTypeCreate(),
            'packing_slip_print' => $this->getPackingSlipPrint(),
            'warehouse_id' => $this->getWarehouseId(),
            'DDP' => $this->getDDP(),
            'is_hash' => $this->getIsHash(),
            'currency' => $this->getCurrency(),
            'category' => $this->getCategory(),
            'purchase_order_number' => $this->getPurchaseOrderNumber(),
        ];
    }

    public function toArrayView()
    {
        $delivery = Deliveries::getById($this->getDeliveryId())->toArray();
        $shippingData = OrderPackages::getBoxes($this->getId());
        unset($delivery['id']);
        $user = Users::getById($this->getUserId())->getLogin();
        $supplier = Suppliers::getById($this->getSupplierId());
        ['name' => $name, 'special' => $special] = Shippings::getServiceParamsById($this->getShippingServicesId());
        return [
            'id' => $this->getId(),
            'delivery' => $delivery,
            'carrier' => Carriers::getById($this->getCarrierId())->getCode(),
            'despatch_date' => $this->getDespatchDate(),
            'despatched_date' => $this->getDespatchedDate(),
            'ref' => $this->getRef(),
            'picking' => $this->getPicking(),
            'packing' => $this->getPacking(),
            'notes' => $this->getNotes(),
            'create_date' => $this->getCreateDate(),
            'supplier' => $supplier->getName(),
            'user' => $user,
            'channel' => $this->getShopId(),
            'status' => $this->getStatusName(),
            'shipping_service' => $name,
            'special' => $special,
            'tracking_numbers' => array_column($shippingData, 'tracking_number'),
            'category' => $this->getCategory(),
            'purchase_order_number' => $this->getPurchaseOrderNumber(),
        ];
    }

    public function toArrayVirtual()
    {
        $delivery = Deliveries::getById($this->getDeliveryId())->toArray();
        unset($delivery['id']);
        $supplier = Suppliers::getById($this->getSupplierId());
        $shipping_service = ShippingServices::getById($this->getShippingServicesId());
        $carrier = Carriers::getById($this->getCarrierId());
        return [
            'id' => $this->getId(),
            'create_date' => $this->getCreateDateInt(),
            'ref2' => $this->getRef2(),
            'delivery' => $delivery,
            'type_create' => $this->getTypeCreate(),
            'product_price' => $supplier->getProductPrice(),
            'shop_id' => $this->getShopId(),
            'warehouse_id' => $this->getWarehouseId(),
            'carrier' => !empty($carrier) ? $carrier->getCode() : '',
            'shipping_service' => !empty($shipping_service) ? $shipping_service->getCode() : '',
            'currency' => $this->getCurrency(),
            'category' => $this->getCategory(),
            'purchase_order_number' => $this->getPurchaseOrderNumber(),
        ];
    }
}