<?php

namespace app\backend\models;

use app\core\entities\ChannelHistory;
use app\core\entities\FlatIntegration;
use app\core\entities\SFTPExport;
use app\core\entities\Shop;
use app\core\entities\UserChannel;
use app\core\models\Channels;
use app\core\services\crypto\Redactor;

class ChannelHistories extends \app\core\models\ChannelHistories
{
    private static function prepareData($channel_supplier_id, $user_id, $warehouse_id, $type, $addition = []): array
    {
        return [
            'channel_supplier_id' => $channel_supplier_id,
            'user_id' => $user_id,
            'warehouse_id' => $warehouse_id,
            'type' => $type,
            // Connection credentials, SFTP and flat passwords: history keeps only the fact of a change
            'addition' => json_encode(is_array($addition) ? Redactor::redact($addition) : $addition)
        ];
    }

    private static function eventIntegration(UserChannel $user_channel, $type, $addition = [], $user_id = 0, $warehouse_id = 0)
    {
        self::setEvent(
            self::prepareData(
                $user_channel->getId(),
                $user_id ?: $user_channel->getUserId(),
                $warehouse_id ?: $user_channel->getWarehouseId(),
                $type,
                $addition)
        );
    }

    public static function connectUserChannel(UserChannel $user_channel)
    {
        self::eventIntegration($user_channel, ChannelHistory::TYPE_CONNECT, ['credentials' => $user_channel->getCredentials()]);
    }

    public static function updateShopConnection(Shop $shop, $user_id, $warehouse_id)
    {
        self::setEvent(
            self::prepareData(
                $shop->getId(),
                $user_id,
                $warehouse_id,
                ChannelHistory::TYPE_UPDATE_SHOP,
                $shop->getCredentials())
        );
    }

    public static function disableConnection(UserChannel $user_channel, $user_id, $data)
    {
        self::eventIntegration($user_channel, ChannelHistory::TYPE_DISABLE_CONNECT, $data, $user_id);
    }

    public static function restoreConnection(UserChannel $user_channel, $user_id, $data)
    {
        self::eventIntegration($user_channel, ChannelHistory::TYPE_RESTORE_CONNECT, $data, $user_id);
    }

    public static function updateShop($shop_id, $supplier_id, $user_id, $warehouse_id, $data)
    {
        $user_channel = Channels::getUserChannel([
            'shop_id' => $shop_id,
            'supplier_id' => $supplier_id,
        ]);
        if (empty($user_channel))
            return;
        self::eventIntegration(
            $user_channel->getUserChannel(),
            ChannelHistory::TYPE_UPDATE_CONNECT,
            array_merge(['shop_id' => $shop_id], $data),
            $user_id,
            $warehouse_id
        );
    }

    public static function setRisk(UserChannel $user_channel, $user_id, $risk)
    {
        self::eventIntegration($user_channel, ChannelHistory::TYPE_SET_RISK, $risk, $user_id);
    }

    public static function syncLocations(UserChannel $user_channel, $warehouse_id, $user_id, $data)
    {
        self::eventIntegration($user_channel, ChannelHistory::TYPE_SYNC_LOCATIONS, $data, $user_id, $warehouse_id);
    }

    public static function setPrimaryShop(UserChannel $user_channel, $user_id, $warehouse_id, $primary_shop)
    {
        self::eventIntegration($user_channel, ChannelHistory::TYPE_PRIMARY_SHOP, $primary_shop, $user_id, $warehouse_id);
    }

    public static function shippingMapping($shop_id, $warehouse_id, $user_id, $data)
    {
        $user_channel = Channels::getUserChannel([
            'shop_id' => $shop_id
        ]);
        if (empty($user_channel))
            return;
        $data['mapping_id'] = $data['id'];
        unset($data['id']);
        self::eventIntegration(
            $user_channel->getUserChannel(),
            ChannelHistory::TYPE_SHIPPING_MAPPING,
            array_merge(['shop_id' => $shop_id], $data),
            $user_id,
            $warehouse_id
        );
    }

    private static function eventSFTP(SFTPExport $sftp, $user_id, $type, $addition = [])
    {
        self::setEvent(
            self::prepareData(
                $sftp->getId(),
                $user_id,
                null,
                $type,
                ['id' => $sftp->getId(), 'channel' => Channels::SFTP_CHANNEL] + $addition
            )
        );
    }

    public static function connectSFTP(SFTPExport $sftp, $user_id)
    {
        self::eventSFTP($sftp, $user_id, ChannelHistory::TYPE_CONNECT, [
            'login' => $sftp->getLogin(),
            'password' => $sftp->getPassword(),
        ]);
    }

    public static function disableSFTP(SFTPExport $sftp, $user_id)
    {
        self::eventSFTP($sftp, $user_id, ChannelHistory::TYPE_DISABLE_CONNECT);
    }

    public static function restoreSFTP(SFTPExport $sftp, $user_id)
    {
        self::eventSFTP($sftp, $user_id, ChannelHistory::TYPE_RESTORE_CONNECT);
    }

    public static function updateSFTP(SFTPExport $sftp, $user_id, $history)
    {
        self::eventSFTP($sftp, $user_id, ChannelHistory::TYPE_UPDATE_CONNECT, $history);
    }

    private static function eventFlat(FlatIntegration $flat, $user_id, $type, $addition = [])
    {
        self::setEvent(
            self::prepareData(
                $flat->getId(),
                $user_id,
                $flat->getWarehouseId(),
                $type,
                ['id' => $flat->getId(), 'channel' => Channels::FLAT_CHANNEL] + $addition
            )
        );
    }

    public static function disableFlat(FlatIntegration $flat, $user_id)
    {
        self::eventFlat($flat, $user_id, ChannelHistory::TYPE_DISABLE_CONNECT);
    }

    public static function restoreFlat(FlatIntegration $flat, $user_id)
    {
        self::eventFlat($flat, $user_id, ChannelHistory::TYPE_RESTORE_CONNECT);
    }

    public static function updateFlat(FlatIntegration $flat, $user_id, $history)
    {
        self::eventFlat($flat, $user_id, ChannelHistory::TYPE_UPDATE_CONNECT, $history);
    }
}
