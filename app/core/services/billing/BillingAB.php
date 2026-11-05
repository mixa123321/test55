<?php

namespace app\core\services\billing;

use app\backend\models\FTPUsers;
use app\backend\models\Warehouses;
use app\core\entities\ShippingService;
use app\core\entities\Warehouse;
use app\core\helpers\formatters\FormatterCSV;
use app\core\models\Billings;
use app\core\entities\Billing as BillingEntity;
use app\core\models\InvoicesBilling;
use app\core\models\Messages;
use app\core\services\billing\AB\ASNs;
use app\core\services\billing\AB\OrderPackages;
use app\core\services\billing\AB\Orders;
use app\core\services\billing\AB\SKUStorages;
use app\core\statical\WithDate;
use app\core\helpers\FileWrapper;

class BillingAB extends Billing
{
    const MAX_RECORDS = 10000;

    const EMAILS = [
        's.ustenko@v-tersolutions.com',
        'oliver.allsworth@airbusiness.com',
        'Matthew.Hirst@airbusiness.com',
    ];

    const SECTIONS = [
        'ChargeStorage',        // Charge for location ST & PF
        'Picks',                // Picks
        'CullingPicks',         // Culling picks
        'BundlingPicks',        // Bundling picks
        'Packagings',           // Packagings
        'CullingPackagings',    // Culling packagings
        'BundlingPackagings',   // Bundling packagings
        'Booking',              // Booking in charges
    ];

    private const TEMPLATE = [
        'Id' => 0,
        'ShipperId' => 0,
        'ShipperName' => '',
        'ServiceCode' => '',
    ];

    /** @var Warehouse */
    private static $warehouse;
    private static $now_date;
    private static $start_date;
    private static $last_date;

    private static function createFeeds()
    {
        self::getInterval(self::$start_date, self::$last_date);
        $files = self::createReport();
        foreach ($files as $filename) {
            $data = [
                'create_at' => date(DATE_ATOM, self::$now_date),
                'warehouse_id' => self::$warehouse->getId(),
                'filename' => $filename,
                'status' => BillingEntity::NEW
            ];
            $feed = new BillingEntity($data);
            $feed->save(array_keys($data));
        }
    }

    private static function createReport(): array
    {
        $nums = 0;
        $row = 0;
        $files = [];
        $data = self::createNewReport($nums);
        foreach (self::SECTIONS as $section) {
            $func = self::class . "::get{$section}Section";
            $services = $func();
            foreach ($services as $service) {
                if ($row === self::MAX_RECORDS) {
                    $files[] = self::saveServices($nums, $data);
                    $nums++;
                    $data = self::createNewReport($nums);
                    $row = 0;
                }
                $service['Id'] = $row;
                $data['Data'][] = $service;
                $row++;
            }
        }
        if (!$nums || $row)
            $files[] = self::saveServices($nums, $data);
        return $files;
    }

    private static function saveServices($nums, $data): string
    {
        $filename = sprintf("billing_%03d_%s_%06d.json", self::$warehouse->getId(), date('Ym', self::$now_date), $nums);
        $path = BILLING_STORAGE . DIRECTORY_SEPARATOR . self::$warehouse->getBilling();
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($path)))->handleDir($path);
        $file_wrapper->writeFileString($path . DIRECTORY_SEPARATOR . $filename, json_encode($data));
        return $filename;
    }

    private static function createNewReport($nums): array
    {
        return [
            'Id' => sprintf("%s-%03d-%s-%06d", self::$warehouse->getBilling(), self::$warehouse->getId(), date('Ym', self::$now_date), $nums),
            'Data' => []
        ];
    }

    public static function sendFeed(BillingEntity $feed, int $invoice_billing_id): bool
    {
        $filename = BILLING_STORAGE . DIRECTORY_SEPARATOR . self::$warehouse->getBilling() . DIRECTORY_SEPARATOR . $feed->getFilename();
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($filename)));
        if (!$file_wrapper->isFile($filename))
            return self::addError($feed, ['errors' => 'File is not found']);
        $data = stream_get_contents($file_wrapper->getStream($filename));
        if (empty($data))
            return self::addError($feed, ['errors' => 'File is empty']);
        $res = self::sendMail($feed->getFilename(), $data);
        if (!$res[0])
            return self::addError($feed, $res[1]);
        self::sendMessage($feed->getCreatedAt(), ['errors' => "It's done"]);
        $feed->setUpdatedAt(date(DATE_ATOM, time()));
        $feed->setResponse(json_encode($res[1]));
        $feed->setStatus(BillingEntity::DONE);
        $feed->setInvoiceBillingId($invoice_billing_id);
        $feed->save(['id', 'updated_at', 'status', 'response', 'invoice_billing_id']);
        return true;
    }

    private static function sendMail($filename, $content): array
    {
        $content = chunk_split(base64_encode($content));
        $uid = md5(uniqid(time()));
        $header = "From: notifier@" . API_DOMAIN . PHP_EOL;
        $header .= "MIME-Version: 1.0" . PHP_EOL;
        $header .= "Content-Type: multipart/mixed; boundary=\"$uid\"";
        $message = "--$uid" . PHP_EOL;
        $message .= "Content-Type: text/html; charset=utf-8" . PHP_EOL;
        $message .= "Content-Transfer-Encoding: 8bit" . PHP_EOL . PHP_EOL;
        $subject = 'Billing ' . date('m Y', self::$now_date) . ' (' . self::$warehouse->getName() . ')';
        $message .= "
            <html>
                <head>
                    <title>$subject</title>
                </head>
                <body>
                    Report in $filename
                </body>
            </html>" . PHP_EOL;
        $message .= "--$uid" . PHP_EOL;
        $message .= "Content-Type: text/csv; name=\"$filename\"" . PHP_EOL;
        $message .= "Content-Transfer-Encoding: base64" . PHP_EOL;
        $message .= "Content-Disposition: attachment; filename=\"$filename\"" . PHP_EOL;
        $message .= $content . PHP_EOL;
        $message .= "--$uid--";
        if (!mail(implode(', ', self::EMAILS), $subject, $message, $header))
            return [false, 'There was an error sending the report'];
        return [true, "It's done"];
    }

    private static function addError(BillingEntity $feed, $error): bool
    {
        if (!empty($error['errors']))
            self::sendMessage($feed->getCreatedAt(), $error);
        $feed->setUpdatedAt(date(DATE_ATOM, time()));
        $feed->setStatus(BillingEntity::ERROR);
        $feed->setResponse($error);
        $data = $feed->toArray();
        unset($data['id']);
        $feed->save(array_keys($data));
        return false;
    }

    private static function sendMessage($date, $error)
    {
        $message_title = "<b>Billing " . self::$warehouse->getName() . " (" . self::$warehouse->getBilling() . ", " . date('Y-m-d', strtotime($date)) . ")</b>\n";
        if (!is_array($error['errors']))
            $message = "<b>{$error['errors']}</b>";
        else
            $message = "Code: <b>{$error['errors']['code']}</b>\n<i>Detail information can be found at WMS</i>";
//        (new TelegramNotifier(Notifier::GROUP_INTEGRATION, $message_title . $message, TelegramNotifier::PARSE_HTML))->send();
        Messages::addDebugInfo($message_title, $message, Messages::GROUP_INTEGRATION, Messages::TEST_PARAMETERS);
    }

    /** @throws */
    private static function getInterval(&$start_date, &$last_date)
    {
        $start_date = (new \DateTime(date(WithDate::DATE_SIMPLE_FORMAT, self::$now_date)))
            ->modify('first day of')
            ->modify('Monday this week')
            ->format(WithDate::DATE_SQL_FORMAT);
        $last_date = (new \DateTime(date(WithDate::DATE_SIMPLE_FORMAT, self::$now_date)))
            ->modify('last day of')
            ->modify('Sunday last week')
            ->setTime(23, 59, 59)
            ->format(WithDate::DATE_SQL_FORMAT);
    }

    private static function getChargeStorageSection(): array
    {
        $storages = SKUStorages::getStorageV2(self::$warehouse->getId(), self::$start_date, self::$last_date);
        $rows = [];
        foreach ($storages as $type_id => $storage) {
            foreach ($storage as $supplier) {
                $data = self::TEMPLATE;
                $data['ShipperId'] = $supplier['sage_id'];
                $data['ShipperName'] = $supplier['name'];
                $data['ServiceCode'] = "ChargeStorage$type_id";
                $data['Items'] = (int)$supplier['units'];
                $rows[] = $data;
            }
        }
        return $rows;
    }

    private static function getPicksSection(): array
    {
        return self::getDataPicks('Picks');
    }

    private static function getCullingPicksSection(): array
    {
        return self::getDataPicks('CullingPicks', ShippingService::SPECIAL_CULLING);
    }

    private static function getBundlingPicksSection(): array
    {
        return self::getDataPicks('BundlingPicks', ShippingService::SPECIAL_BUNDLING);
    }

    private static function getDataPicks(string $service_code, int $is_special = 0): array
    {
        $rows = [];
        $picks = Orders::getPicks(self::$warehouse->getId(), self::$start_date, self::$last_date, $is_special);
        foreach ($picks as $pick) {
            $data = self::TEMPLATE;
            $data['ShipperId'] = $pick['sage_id'];
            $data['ShipperName'] = $pick['sup_name'];
            $data['ServiceCode'] = $service_code;
            $data['OrderId'] = (int)$pick['order_id'];
            $data['Barcode'] = $pick['barcode'];
            $data['SKU'] = $pick['name'];
            $data['Items'] = (int)$pick['quantity'];
            $rows[] = $data;
        }
        if (!$is_special)
            self::saveToSFTPDistributions($picks);
        return $rows;
    }

    private static function getPackagingsSection(): array
    {
        return self::getDataPackagings('Packagings');
    }

    private static function getCullingPackagingsSection(): array
    {
        return self::getDataPackagings('CullingPackagings', ShippingService::SPECIAL_CULLING);
    }

    private static function getBundlingPackagingsSection(): array
    {
        return self::getDataPackagings('BundlingPackagings', ShippingService::SPECIAL_BUNDLING);
    }

    private static function getDataPackagings(string $service_code, int $is_special = 0): array
    {
        $rows = [];
        $packagings = OrderPackages::getPackagings(self::$warehouse->getId(), self::$start_date, self::$last_date, $is_special);
        foreach ($packagings as $packaging) {
            $data = self::TEMPLATE;
            $data['ShipperId'] = $packaging['sage_id'];
            $data['ShipperName'] = $packaging['sup_name'];
            $data['ServiceCode'] = $service_code;
            $data['OrderId'] = (int)$packaging['order_id'];
            $data['Packaging'] = $packaging['name'];
            $rows[] = $data;
        }
        return $rows;
    }

    private static function getBookingSection(): array
    {
        $rows = [];
        $bookings = ASNs::getBooking(self::$warehouse->getId(), self::$start_date, self::$last_date);
        foreach ($bookings as $booking) {
            $data = self::TEMPLATE;
            $data['ShipperId'] = $booking['sage_id'];
            $data['ShipperName'] = $booking['sup_name'];
            $data['ServiceCode'] = 'Booking';
            $data['ASNId'] = (int)$booking['ASN_id'];
            $data['ASNRef'] = $booking['ref'];
            $data['Barcode'] = $booking['barcode'];
            $data['SKU'] = $booking['name'];
            $data['Items'] = (int)$booking['qty'];
            $rows[] = $data;
        }
        return $rows;
    }

    public static function report($warehouse_id, $create_date = null)
    {
        self::$warehouse = Warehouses::getById($warehouse_id);
        if (empty(self::$warehouse))
            return;
        $nums = 0;
        if (is_null($create_date)) {
            $nums = Billings::getCountFeeds(self::$warehouse->getId());
            self::$now_date = time() - 24*60*60;
        } elseif (is_string($create_date))
            self::$now_date = strtotime($create_date);
        else
            self::$now_date = $create_date;
        if (!$nums)
            self::createFeeds();
        $feeds = Billings::getNewFeeds(self::$warehouse->getId());
        if (empty($feeds))
            return;
        foreach ($feeds as $feed) {
            $date = explode('_', $feed['filename'])[2];
            $invoice_billing_id = InvoicesBilling::add((int)substr($date, 0, 4), (int)substr($date, 4));
            self::sendFeed(new BillingEntity($feed), $invoice_billing_id);
        }
    }

    private static function getSFTPReportName(int $supplier_id, string $name): string
    {
        return sprintf(
            "%s-%03d-%s-%03d-%s.csv",
            self::$warehouse->getBilling(),
            self::$warehouse->getId(),
            date('Ym', self::$now_date),
            $supplier_id,
            $name);
    }

    public static function saveToSFTPDistributions($picks)
    {
        $suppliers_orders = [];
        foreach ($picks as $pick) {
            if (empty($suppliers_orders[$pick['id']]))
                $suppliers_orders[$pick['id']] = [];
            if (empty($suppliers_orders[$pick['id']][$pick['order_id']]))
                $suppliers_orders[$pick['id']][$pick['order_id']] = 1;
        }
        if (empty($suppliers_orders))
            return;
        $login = FTPUsers::getWarehouseLogin(self::$warehouse);
        $path = SFTP_STORAGE . DIRECTORY_SEPARATOR . $login . DIRECTORY_SEPARATOR . FTPUsers::DIR_BILLING;
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($path)))->handleDir($path);
        foreach ($suppliers_orders as $supplier_id => $orders) {
            $distributions = Orders::getDistributions(array_keys($orders));
            if (empty($distributions))
                continue;
            $temp = tmpfile();
            $tmp_file = stream_get_meta_data($temp)['uri'];

            $filename = $path . DIRECTORY_SEPARATOR . self::getSFTPReportName($supplier_id, 'distributions');
            $csv = new FormatterCSV($tmp_file);
            $csv->addHeader(['CarrierService', 'Destination Country', 'Total Number of Packages', 'Total Weight']);
            $csv->addData($distributions);

            fseek($temp, 0);
            $file_wrapper->writeFileResource($filename, $temp);
        }
    }

    public static function reportReturns($warehouse_id, $create_date = null)
    {
    }

    public static function reportVirtual($warehouse_id, $create_date = null)
    {
    }
}