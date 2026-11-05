<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\helpers\HTTP;
use app\core\Storage;

class Supplier extends Entity
{
    const ACTIVE            = 0;
    const LOCKED            = 1;

    const STATUSES = [
        self::ACTIVE => 'Active',
        self::LOCKED => 'Locked'
    ];

    const TYPE_REGULAR      = 1;
    const TYPE_CROSS_DOCK   = 2;
    const TYPE_WHOLESALE    = 3;

    private $id;
    private $name;
    private $contact;
    private $type_account;
    private $day30;
    private $user_id;
    private $del;
    private $logo;
    private $email;
    private $shopify_barcode_field;
    private $order_inserts;
    private $default_description_of_goods;
    private $warehouses = [];
    private $product_price = 1;
    private $packing_slip_additional_information;
    private $shopify_locations;
    private $offer_exchanges;
    private $offer_returns;
    private $has_weeks_before_expiry;
    private $weeks_before_expiry;
    private $custom_return_doc;
    private $custom_return;
    private $packing_slip_batch_expired;
    private $no_action_returns;
    private $utilise_despatch_date;
    private $days_before_despatch;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = (string)$name;
    }

    public function getContact()
    {
        return $this->contact;
    }

    public function setContact($contact)
    {
        $this->contact = (string)$contact;
    }

    public function getTypeAccount()
    {
        return $this->type_account;
    }

    public function setTypeAccount($type_account)
    {
        $this->type_account = (int)$type_account;
    }

    public function getDay30()
    {
        return $this->day30;
    }

    public function setDay30($day30)
    {
        $this->day30 = (int)$day30;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function getDel()
    {
        return $this->del;
    }

    public function setDel($del)
    {
        $this->del = (int)$del;
    }

    public function getLogo()
    {
        return $this->logo;
    }

    public function setLogo($name)
    {
        $this->logo = (string)$name;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = (string)$email;
    }

    public function getShopifyBarcodeField()
    {
        return $this->shopify_barcode_field;
    }

    public function setShopifyBarcodeField($shopify_barcode_field)
    {
        $this->shopify_barcode_field = (string)$shopify_barcode_field;
    }

    public function getOrderInserts()
    {
        return $this->order_inserts;
    }

    public function setOrderInserts($order_inserts)
    {
        $this->order_inserts = (int)$order_inserts;
    }

    public function getDefaultDescriptionOfGoods()
    {
        return $this->default_description_of_goods;
    }

    public function setDefaultDescriptionOfGoods($default_description_of_goods): void
    {
        $this->default_description_of_goods = substr((string)$default_description_of_goods, 0, 70);
    }

    public function getWarehouses()
    {
        return $this->warehouses;
    }

    public function setWarehouses($warehouses)
    {
        if (empty($warehouses))
            $warehouses = [];
        elseif (is_string($warehouses))
            $warehouses = json_decode($warehouses, true);
        $this->warehouses = $warehouses;
    }

    public function getProductPrice()
    {
        return $this->product_price;
    }

    public function setProductPrice($price)
    {
        $this->product_price = (int)$price;
    }

    public function getPackingSlipAdditionalInformation()
    {
        return $this->packing_slip_additional_information;
    }

    public function setPackingSlipAdditionalInformation($packing_slip_additional_information): void
    {
        $this->packing_slip_additional_information = substr((string)$packing_slip_additional_information, 0, 255);
    }

    public function getShopifyLocations()
    {
        return $this->shopify_locations;
    }

    public function setShopifyLocations($shopify_locations)
    {
        $this->shopify_locations = (int)$shopify_locations;
    }

    public function getOfferExchanges()
    {
        return $this->offer_exchanges;
    }

    public function setOfferExchanges($offer_exchanges): void
    {
        $this->offer_exchanges = $offer_exchanges;
    }

    public function isOfferExchanges(): bool
    {
        return !empty($this->offer_exchanges);
    }

    public function getOfferReturns()
    {
        return $this->offer_returns;
    }

    public function setOfferReturns($offer_returns): void
    {
        $this->offer_returns = $offer_returns;
    }

    public function isOfferReturns(): bool
    {
        return !empty($this->offer_returns);
    }

    public function getHasWeeksBeforeExpiry()
    {
        return $this->has_weeks_before_expiry;
    }

    public function setHasWeeksBeforeExpiry($has_weeks_before_expiry): void
    {
        $this->has_weeks_before_expiry = $has_weeks_before_expiry;
    }

    public function hasWeeksBeforeExpiry(): bool
    {
        return !empty($this->has_weeks_before_expiry);
    }

    public function getWeeksBeforeExpiry(): int
    {
        return (int)$this->weeks_before_expiry;
    }

    public function setWeeksBeforeExpiry($weeks_before_expiry): void
    {
        $this->weeks_before_expiry = (int)$weeks_before_expiry;
    }

    public function getCustomReturn()
    {
        return $this->custom_return;
    }

    public function setCustomReturn($custom_return): void
    {
        $this->custom_return = (bool)$custom_return;
    }

    public function isCustomReturn(): bool
    {
        return !empty($this->custom_return);
    }

    public function getCustomReturnDoc()
    {
        return $this->custom_return_doc;
    }

    public function setCustomReturnDoc($custom_return_doc): void
    {
        $this->custom_return_doc = $custom_return_doc;
    }

    public function getCustomReturnDocPath(): ?string
    {
        return Storage::getFile(CUSTOM_RETURN_DOC_STORAGE, $this->getCustomReturnDoc());
    }

    public function existCustomReturnDocFile(): bool
    {
        return !empty($this->getCustomReturnDoc()) && $this->getCustomReturnDocPath();
    }

    public function getUploadCustomReturnDocUrl(): string
    {
        return $this->existCustomReturnDocFile() ? (HTTP::getDomain() . "/suppliers/get_custom_return_doc_file?id=" . $this->getId()) : '';
    }

    public function getPackingSlipBatchExpired()
    {
        return $this->packing_slip_batch_expired;
    }

    public function setPackingSlipBatchExpired($packing_slip_batch_expired): void
    {
        $this->packing_slip_batch_expired = $packing_slip_batch_expired;
    }

    public function isPackingSlipBatchExpired(): bool
    {
        return !empty($this->packing_slip_batch_expired);
    }

    public function getNoActionReturns()
    {
        return $this->no_action_returns;
    }

    public function setNoActionReturns($no_action_returns)
    {
        $this->no_action_returns = (int)$no_action_returns;
    }

    public function getUtiliseDespatchDate()
    {
        return $this->utilise_despatch_date;
    }

    public function setUtiliseDespatchDate($value)
    {
        $this->utilise_despatch_date = (bool)$value;
    }

    public function getDaysBeforeDespatch()
    {
        return $this->days_before_despatch;
    }

    public function setDaysBeforeDespatch($value)
    {
        $this->days_before_despatch = (int)$value;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (isset($data['warehouses']) && is_array($data['warehouses'])) {
            if (!empty($data['warehouses']))
                $data['warehouses'] = json_encode(array_values($data['warehouses']));
            else
                $data['warehouses'] = '[]';
        }
        $id = Mysql::saveRow('suppliers', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'contact' => $this->getContact(),
            'type_account' => $this->getTypeAccount(),
            'day30' => $this->getDay30(),
            'user_id' => $this->getUserId(),
            'del' => $this->getDel(),
            'logo' => $this->getLogo(),
            'email' => $this->getEmail(),
            'shopify_barcode_field' => $this->getShopifyBarcodeField(),
            'order_inserts' => $this->getOrderInserts(),
            'default_description_of_goods' => $this->getDefaultDescriptionOfGoods(),
            'warehouses' => $this->getWarehouses(),
            'product_price' => $this->getProductPrice(),
            'packing_slip_additional_information' => $this->getPackingSlipAdditionalInformation(),
            'shopify_locations' => $this->getShopifyLocations(),
            'offer_exchanges' => $this->getOfferExchanges(),
            'offer_returns' => $this->getOfferReturns(),
            'has_weeks_before_expiry' => $this->getHasWeeksBeforeExpiry(),
            'weeks_before_expiry' => $this->getWeeksBeforeExpiry(),
            'custom_return_doc' => $this->getCustomReturnDoc(),
            'custom_return' => $this->getCustomReturn(),
            'packing_slip_batch_expired' => $this->getPackingSlipBatchExpired(),
            'no_action_returns' => $this->getNoActionReturns(),
            'utilise_despatch_date' => $this->getUtiliseDespatchDate(),
            'days_before_despatch' => $this->getDaysBeforeDespatch(),
        ];
    }
}
