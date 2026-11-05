<?php

namespace app\core\models;

use app\backend\models\Deliveries as DeliveriesModel;
use app\backend\models\Orders as OrdersModel;
use app\backend\models\OrderSKUs as OrderSKUsModel;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\SupplierWarehouses;
use app\core\dbal\SafeMysql;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\RetainMeCredentials;
use app\core\entities\Shipping;
use app\core\entities\Supplier;
use app\core\entities\Warehouse;
use app\core\Environment;
use app\core\helpers\CURL;
use app\core\helpers\Debug;
use app\core\helpers\File;
use app\core\helpers\FPDFExtended;
use app\core\helpers\FPDFExtendedUTF8;
use app\core\helpers\FPDIFacade;
use app\core\helpers\Str;
use app\core\statical\Currencies;
use app\core\statical\Sources;
use Exception;
use app\core\helpers\FileWrapper;

class PackingSlips
{
    const TYPE_GENERIC = 'generic';
    const TYPE_VOKE = 'voke';
    const TYPE_RETAIN_ME = 'retain_me';
    const TYPE_NONE = 'none';

    const BARCODE_HEIGHT = 90;

    public static function getTypes()
    {
        return [
            self::TYPE_NONE => "None",
            self::TYPE_GENERIC => "Generic",
            self::TYPE_VOKE => "Voke",
            self::TYPE_RETAIN_ME => "Retain.me",
        ];
    }

    public static function draw($order_id, $source_id)
    {
        $order = OrdersModel::getById($order_id);
        if ($order === null) {
            Debug::sendDebugInfo("Packing slip issue: order is null", "Order id: $order_id");
            return false;
        }
        $supplier = Suppliers::getById($order->getSupplierId());
        if ($supplier === null) {
            Debug::sendDebugInfo("Packing slip issue: supplier is null", "Order id: $order_id");
            return false;
        }
        $delivery = DeliveriesModel::getById($order->getDeliveryId());
        if ($delivery === null) {
            Debug::sendDebugInfo("Packing slip issue: delivery is null", "Order id: $order_id");
            return false;
        }
        $data = [
            'order' => $order,
            'supplier' => $supplier,
            'delivery' => $delivery,
            'skus' => OrderSKUsModel::getSKUs($order->getId()),
        ];
        switch (SuppliersWarehouses::getPackingSlipType($order->getWarehouseId(), $supplier->getId(), $order->getShopId())) {
            case self::TYPE_GENERIC:
                self::drawGeneric($data);
                break;
            case self::TYPE_VOKE:
                self::drawVoke($data);
                break;
            case self::TYPE_RETAIN_ME:
                $data['requests'] = ['order', 'print'];
                $data['source_id'] = $source_id;
                return ['retain_me' => self::callRetainMe($data)];
        }
        return true;
    }

    private static function drawGeneric(array $data)
    {
        /** @var Order $order */
        $order = $data['order'];
        /** @var Supplier $supplier */
        $supplier = $data['supplier'];
        /** @var Delivery $delivery */
        $delivery = $data['delivery'];
        /** @var Warehouse $warehouse */
        $warehouse = Warehouses::getById($order->getWarehouseId());
        require(ROOT_PATH . VENDOR_PATH . '/FPDF/tfdpf/tfpdf.php');


        $pdf = new FPDFExtendedUTF8('P', 'pt', 'A4');
        $pdf->SetTitle("Packing slip #{$order->getRef()}");
        $pdf->SetFillColor(230, 228, 223);
        $pdf->SetDrawColor(145, 144, 141);
        $pdf->W = $pdf->GetPageWidth() - 55;
        $pdf->AddPage();
        $pdf->SetFont('Roboto', 'B', 15);
        $pdf->originX = $pdf->GetX();
        $logoH = self::drawGenericLogo($pdf, $supplier);
        $pdf->SetY(max(self::BARCODE_HEIGHT + 20, $logoH));
        $pdf->Cell(0, 0, 'Order Packing Sheet', 0, 1, "C");
        $pdf->SetY($pdf->GetY() + 30);
        $barcode = new BarCode128($order->getId(), self::BARCODE_HEIGHT, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf', 7);
        $barcode_margin = 481;
        $barcode_width = $barcode->Width() > 87 ? 87 : $barcode->Width();
        $pdf->Image($barcode->getImage(), $barcode_margin, $pdf->originX, $barcode_width, $barcode->Height(), 'PNG');
        $pdf->SetFont('Roboto', 'B', 11);
        $pdf->SetY($pdf->GetY() + 20);
        self::drawGenericAddress($pdf, $supplier, $delivery, $warehouse->getId());
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + $pdf->W, $pdf->GetY());
        self::drawGenericOrderReference($pdf, $order);
        $prepared_skus = self::prepareOrderSkusByBatch($order, $data['skus']);
        self::drawGenericSKUs($pdf, $prepared_skus, $supplier->isPackingSlipBatchExpired());
//        $pdf->Write(9, '_ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _ _');
        self::drawGenericAdditionInfo($pdf, $supplier);

        if ($supplier->isOfferReturns()) {
            if ($supplier->isCustomReturn())
                self::customReturnBlock($pdf, $supplier, $order, $barcode, $barcode_margin);
            else {
                self::drawGenericReturns($pdf, $warehouse);
                $pdf->SetY($pdf->GetY() + 5);
                self::drawGenericSKUs($pdf, $prepared_skus, $supplier->isPackingSlipBatchExpired(), true);
                self::newLine($pdf, 240);
                $pdf->SetY($pdf->GetY() + 30);
                $pdf->SetFont('Roboto', 'l', 9);
                $pdf->Write(9, 'Please write in your own words below why you have decided to refund' . ($supplier->isOfferExchanges() ? ' / exchange' : '') . ' the above product(s) ');
                $pdf->SetFont('Roboto', 'B', 8);
                $pdf->Write(9, '(YOU MUST INCLUDE THIS PAPERWORK WITH YOUR RETURN)');
                $pdf->ln();
                if ($supplier->isOfferExchanges())
                    self::offerExchangesBlock($pdf);
                self::returnBlock($pdf, $barcode, $barcode_margin, $barcode_width);
            }
        }
        $pdf->Output("I", "Packing slip " . $order->getRef(), true);
    }

    private static function customReturnBlock(object &$pdf, Supplier $supplier, Order $order, BarCode128 $barcode, $barcode_margin)
    {
        if (!$supplier->existCustomReturnDocFile())
            return;

        $filename = "Packing_slip_" . $order->getId() . '_';
        $present_file = SUPPLIERS_STORAGE . uniqid($filename, true) . '_' .  time() . '.pdf';
        $pdf->Output('F', $present_file, true);

        try {
            $pdf = FPDIFacade::new();
            $pdf->setTitle("Packing slip #" . $order->getRef());

            // PRESENT FILE START
            $temp = tmpfile();
            fwrite($temp, stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($present_file)))->getStream($present_file)));
            $tmp_file = stream_get_meta_data($temp)['uri'];

            $pageCount = $pdf->setSourceFile($tmp_file);
            for ($i = 0; $i < $pageCount; $i++) {
                $tpl = $pdf->importPage($i + 1, '/MediaBox');
                $pdf->addPage();
                $pdf->useTemplate($tpl);
            }
            unlink($present_file);
            // PRESENT FILE END

            // CUSTOM FILE START
            $temp = tmpfile();
            $path = $supplier->getCustomReturnDocPath();
            fwrite($temp, stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($path)))->getStream($path)));
            $tmp_file = stream_get_meta_data($temp)['uri'];

            $barcode_width = $barcode->Width() > 87 ? 87 : $barcode->Width();
            $pageCount = $pdf->setSourceFile($tmp_file);
            for ($i = 0; $i < $pageCount; $i++) {
                $tpl = $pdf->importPage($i + 1, '/MediaBox');
                $pdf->addPage();
                $pdf->useTemplate($tpl);
                $pdf->Image($barcode->getImage(), $barcode_margin, $pdf->GetPageHeight() - $barcode->Height() - 15, $barcode_width, $barcode->Height(), 'PNG');
            }
            // CUSTOM FILE END
        } catch (Exception $e) {
            @unlink($present_file);
            return;
        }
    }

    private static function newLine(object &$pdf, $possiblyNext = null)
    {
        $maxH = 800;
        $y = is_null($possiblyNext) ? $pdf->GetY() : $pdf->GetY() + $possiblyNext;
        if (($y / $maxH) > 0.95) {
            $pdf->AddPage();
            $pdf->SetY(20);
        }
    }

    private static function drawGenericOrderReference(object &$pdf, Order $order)
    {
        $pdf->SetY($pdf->GetY() + 20);
        $pdf->SetFont('Roboto', 'B', 11);
        $pdf->Cell(100, 15, 'Order Number', 0, 0, "L", true);
        $ref = $order->getRef();
        $maxRefLen = 71;
        if (strlen($order->getRef()) <= $maxRefLen) {
            $pdf->Cell(0, 15, $order->getRef(), 0, 0, "l");
        } else {
            $refArr = str_split($ref, $maxRefLen);
            foreach ($refArr as $ref) {
                $pdf->Cell(0, 15, $ref, 0, 0, "l");
                $pdf->ln();
                $pdf->Cell(100, 15, '', 0, 0, "L");
            }
        }
        $ref2 = $order->getRef2();
        if (!empty($ref2)) {
            $pdf->ln();
            $pdf->Cell(100, 15, '', 0, 0, "L");
            $pdf->Cell(0, 15, $ref2, 0, 0, "l");
        }
        $pdf->SetY($pdf->GetY() + 20);
    }

    private static function drawGenericAddressItem(object &$pdf, $left, $right)
    {
        $pdf->SetX($pdf->originX);
        $pdf->MultiCell($pdf->W * 0.3, 15, $left ?: '-', 0, "L");
        $pdf->SetX($pdf->originX + $pdf->W * 0.3);
        $pdf->MultiCell($pdf->W * 0.4, -15, '', 0, "L");
        $pdf->SetX($pdf->originX + $pdf->W * 0.7);
        $pdf->MultiCell($pdf->W * 0.3, 15, $right ?: '-', 0, "L");
    }

    private static function drawGenericAddress(object &$pdf, object $supplier, object $delivery, $warehouse_id)
    {
        $pdf->Cell($pdf->W * 0.3, 15, "Shipped From", 0, 0, "L", true);
        $pdf->Cell($pdf->W * 0.4, 15, "", 0, 0);
        $pdf->Cell($pdf->W * 0.3, 15, "Shipped To", 0, 0, "L", true);
        $pdf->ln();
        $pdf->SetFont('Roboto', 'L', 10);
        $pdf->SetX($pdf->originX);

        /**
         * @var $supplier Supplier
         * @var $delivery Delivery
         */
        $supplierAddress = SuppliersWarehouses::getAddress($warehouse_id, $supplier->getId());
        self::drawGenericAddressItem($pdf, $supplier->getName(), $delivery->getContact());
        self::drawGenericAddressItem($pdf, $supplierAddress->getAddress1(), $delivery->getAddress1());
        self::drawGenericAddressItem($pdf, $supplierAddress->getAddress2(), $delivery->getAddress2());
        self::drawGenericAddressItem($pdf, $supplierAddress->getAddress3(), $delivery->getAddress3());
        self::drawGenericAddressItem($pdf, $supplierAddress->getTown(), $delivery->getTown());
        self::drawGenericAddressItem($pdf, $supplierAddress->getCounty(), $delivery->getCounty());
        self::drawGenericAddressItem($pdf, $supplierAddress->getPostcode(), $delivery->getPostcode());
        self::drawGenericAddressItem($pdf, $supplierAddress->getCountryId(), $delivery->getCountryId());

        $pdf->SetY($pdf->GetY() + 20);
        self::newLine($pdf);
    }

    private static function drawGenericSKUs(object &$pdf, array $skus, $has_batch_expired, bool $return = false)
    {
        $H = 15;

        $W = [
            1 => $pdf->W * ($has_batch_expired ? 0.3 : 0.42),
            2 => $pdf->W * ($has_batch_expired ? 0.24 : 0.42),
            3 => $pdf->W * ($has_batch_expired ? 0.17 : 0),
            4 => $pdf->W * ($has_batch_expired ? 0.17 : 0),
            5 => $pdf->W * ($has_batch_expired ? 0.12 : 0.16),
        ];

        if (!empty($skus)) {
            $f_sku = array_values($skus)[0];
            self::newLine($pdf, $pdf->GetMultiCellHeightForRow([
                    [$W[1], $f_sku['name']],
                    [$W[2], $f_sku['barcode']],
                    [$W[3], $f_sku['batch']]
                ], $H) + 50);
        }

        $pdf->SetY($pdf->GetY() + 20);
        $pdf->SetFont('Roboto', 'B', 12);
        $pdf->Cell($W[1], $H * ($return ? 2 : 1), 'Product', 1, 0, "L", true);
        $pdf->Cell($W[2], $H * ($return ? 2 : 1), 'Barcode', 1, 0, "L", true);
        if ($has_batch_expired) {
            $pdf->Cell($W[3], $H * ($return ? 2 : 1), 'Batch No.', 1, 0, "L", true);
            $pdf->Cell($W[4], $H * ($return ? 2 : 1), 'Expiry Date', 1, 0, "L", true);
        }

        $pdf->MultiCell($W[5], $H, "Quantity" . ($return ? "\nReturned" : ''), 1, 'J', true);

        $pdf->SetFont('Roboto', 'L', 11);
        foreach ($skus as $sku) {
            $maxColSkuHeight = $pdf->GetMultiCellHeightForRow([
                [$W[1], $sku['name']],
                [$W[2], $sku['barcode']],
                [$W[3], $sku['batch']]
            ], $H);
            self::newLine($pdf, $maxColSkuHeight);
            $save_y = $pdf->GetY();
            $pdf->MultiCell($W[1], $H, $sku['name'], 'T', "L");
            $pdf->SetXY($W[1] + $pdf->originX, $save_y);
            $pdf->MultiCell($W[2], $H, $sku['barcode'], 'T', "L");
            $pdf->SetXY($W[1] + $W[2] + $pdf->originX, $save_y);
            if ($has_batch_expired) {
                $pdf->MultiCell($W[3], $H, $sku['batch'], 'T', "L");
                $pdf->SetXY($W[1] + $W[2] + $W[3] + $pdf->originX, $save_y);
                $pdf->MultiCell($W[4], $H, $sku['expired'], 'T', "L");
                $pdf->SetXY($W[1] + $W[2] + $W[3] + $W[4] + $pdf->originX, $save_y);
            }
            $pdf->MultiCell($W[5], $return ? $maxColSkuHeight : $H, $return ? '' : $sku['quantity'], 'T', "L", $return);
            $pdf->SetY($save_y + $maxColSkuHeight);
            self::newLine($pdf);
        }
    }

    private static function offerExchangesBlock(object &$pdf)
    {
        $pdf->ln();
        $pdf->ln();
        $pdf->SetFont('Roboto', 'l', 9);
        $pdf->Write(9, 'Please tick either Refund or Exchange below: ');
        $pdf->ln();
        $pdf->ln();
        $pdf->SetFont('Roboto', 'B', 10);
        $pdf->Write(9, 'Refund ');
        $pdf->Rect($pdf->GetX() + 5, $pdf->GetY(), 12, 12, '');
        $pdf->SetX($pdf->GetX() + 40);
        $pdf->Write(9, 'Exchange ');
        $pdf->Rect($pdf->GetX() + 5, $pdf->GetY(), 12, 12, '');
        $pdf->ln();
        $pdf->ln();
        $pdf->SetFont('Roboto', 'l', 9);
        $pdf->Write(9, 'If you are exchanging, please write the name of product(s) you wish to receive in the “Any additional comments” section.');
        $pdf->ln();
    }

    private static function returnBlock(object &$pdf, BarCode128 $barcode, $margin, $barcode_width)
    {
        $react_h = 150;
        $pdf->Image($barcode->getImage(), $margin, $pdf->GetY() + $react_h - 50, $barcode_width, $barcode->Height(), 'PNG');
        $pdf->Rect($pdf->GetX(), $pdf->GetY() + 15, $pdf->W, $react_h, '');

        $pdf->Rect($pdf->GetX() + 5, $pdf->GetY() + 20, $pdf->W * 0.4, $react_h - 10, '');

        $pdf->SetFont('Roboto', '', 10);

        $pdf->Rect($pdf->GetX() + 10, $pdf->GetY() + 30, 12, 12, '');
        $pdf->Text($pdf->GetX() + 35, $pdf->GetY() + 40, 'Wrong Size');

        $pdf->Rect($pdf->GetX() + 10, $pdf->GetY() + 60, 12, 12, '');
        $pdf->Text($pdf->GetX() + 35, $pdf->GetY() + 70, 'Not as expected');

        $pdf->Rect($pdf->GetX() + 10, $pdf->GetY() + 90, 12, 12, '');
        $pdf->Text($pdf->GetX() + 35, $pdf->GetY() + 100, 'Wrong item sent');

        $pdf->Rect($pdf->GetX() + 10, $pdf->GetY() + 120, 12, 12, '');
        $pdf->Text($pdf->GetX() + 35, $pdf->GetY() + 130, 'Arrived too late');

        $pdf->Rect($pdf->GetX() + $pdf->W * 0.4 + 20, $pdf->GetY() + 20, $pdf->W * 0.4, $react_h - 10, '');
        $pdf->Text($pdf->GetX() + $pdf->W * 0.4 + 50, $pdf->GetY() + 40, 'Any additional comments:');
    }

    private static function prepareOrderSkusByBatch(Order $order, array $skus)
    {
        $batch_skus = [];
        $setBatch = function ($sku, $batch, $expire, $quantity = null) {
            return [
                'name' => $sku['name'],
                'barcode' => $sku['barcode'],
                'batch' => $batch,
                'expired' => $expire,
                'quantity' => $quantity ?? $sku['quantity']
            ];
        };
        foreach ($skus as $sku) {
            $SkuBatchQuantity = self::getSkuBatchQuantity($order, $sku['id']);
            if (!empty(($SkuBatchQuantity)))
                foreach ($SkuBatchQuantity as $batch)
                    $batch_skus[] = $setBatch($sku, $batch['number'], $batch['expired'], $batch['quantity'] > 0 ? $batch['quantity'] : $sku['quantity']);
            else $batch_skus[] = $setBatch($sku, 'N/A', 'N/A');
        }
        return $batch_skus;
    }

    public static function getSkuBatchQuantity(Order $order, $sku_id)
    {
        $batch_info = [];
        $batch_empty = [
            'number' => 'N/A',
            'expired' => 'N/A',
            'quantity' => 0
        ];
        if (!$order->despatched()) {
            $order_id = (int)$order->getId();
            $sku_id = (int)$sku_id;
            $sql = "
                    SELECT b.number, b.expired,
                        IFNULL(SUM(sl.quantity), 0) as quantity
                            FROM SKU_locations sl
                        LEFT JOIN batches b ON b.SKU_id = sl.SKU_id AND b.id = sl.batch_id
                    WHERE (sl.order_id = {$order_id} or 
                        (sl.order_id IS NULL AND POSITION('2_' IN sl.reason) = 1 AND SUBSTRING_INDEX(sl.reason, '_', -1) = {$order_id}))
                            AND sl.SKU_id = {$sku_id} GROUP BY b.id";
            $batches_res = (new SafeMysql())->fetchAll("
                    SELECT b.number, b.expired,
                        IFNULL(SUM(sl.quantity), 0) as quantity
                            FROM SKU_locations sl
                        LEFT JOIN batches b ON b.SKU_id = sl.SKU_id AND b.id = sl.batch_id
                    WHERE (sl.order_id = :order_id or 
                        (sl.order_id IS NULL AND POSITION('2_' IN sl.reason) = 1 AND SUBSTRING_INDEX(sl.reason, '_', -1) = :order_id))
                            AND sl.SKU_id = :sku_id GROUP BY b.id",
                ['order_id' => $order_id, 'sku_id' => $sku_id]);
            if (!empty($batches_res)) {
                foreach ($batches_res as $b_key => &$batch_res) {
                    if (empty($batch_res['number']) && $batch_res['quantity'] > 0) {
                        $batch_empty['quantity'] += $batch_res['quantity'];
                        unset($batches_res[$b_key]);
                    } else
                        $batch_res['expired'] = date('d/m/Y', strtotime($batch_res['expired']));
                }
                if ($batch_empty['quantity'] > 0)
                    $batches_res[] = $batch_empty;
                $batch_info = $batches_res;
            }
        } else {
            $despatched_items = OrdersOriginalDespatched::getHandledItems($order);
            if (!empty($despatched_items)) {
                foreach ($despatched_items as $despatched_item)
                    if ($sku_id == $despatched_item['SKU_id']) {
                        $batch = Batches::getById($despatched_item['batch_id']);
                        $batch_number = 'N/A';
                        $batch_expired = 'N/A';
                        if ($batch !== null) {
                            $batch_number = $batch->getNumber() ?? 'N/A';
                            $batch_expired = $batch->getExpired() ? date('d/m/Y', strtotime($batch->getExpired())) : 'N/A';
                        }
                        $batch_info[] = [
                            'number' => $batch_number,
                            'expired' => $batch_expired,
                            'quantity' => $despatched_item['quantity'] ?? 0
                        ];
                    }
            } else $batch_info[] = $batch_empty;
        }
        return $batch_info;
    }

    /**
     * @param  object  $pdf
     * @param $supplier ?Supplier
     */
    public static function drawGenericAdditionInfo(object $pdf, ?Supplier $supplier)
    {
        if (!empty($supplier) && !empty($supplier->getPackingSlipAdditionalInformation())) {
            self::newLine($pdf, 90);
            $pdf->SetY($pdf->GetY() - 5);
            $pdf->ln();
//            $pdf->Rect($pdf->GetX(), $pdf->GetY() - 5, $pdf->W, 50, '');
            $pdf->SetFont('Roboto', 'B', 10);
            $pdf->Write(11, $supplier->getPackingSlipAdditionalInformation());
//            $pdf->ln();
        }
    }

    public static function drawGenericReturns(object $pdf, Warehouse $warehouse)
    {
        self::newLine($pdf, 120);
        $pdf->SetY($pdf->GetY() + 20);
        $pdf->SetFont('Roboto', 'b', 12);
        $pdf->Cell($pdf->W, 30, 'Returns', 0, 0, "C");
        $pdf->ln();
        $pdf->Rect($pdf->GetX(), $pdf->GetY() - 25, $pdf->W, 50, '');
        $pdf->SetFont('Roboto', 'l', 9);
        $pdf->Write(11, 'To return part or all of this order, complete the form below and post along with the item(s) to ');
        $pdf->SetFont('Roboto', 'b', 8);
        $pdf->Write(11, implode(', ', $warehouse->getAddress()));
    }

    private static function drawVoke(array $data)
    {
        /** @var Order $order */
        $order = $data['order'];
        $order_number = empty($order->getRef2()) ? $order->getRef() : $order->getRef2();
        /** @var Supplier $supplier */
        $supplier = $data['supplier'];
        $supplierAddress = SuppliersWarehouses::getAddress($order->getWarehouseId(), $supplier->getId());
        /** @var Delivery $delivery */
        $delivery = $data['delivery'];
        require(ROOT_PATH . VENDOR_PATH . '/FPDF/fpdf.php');
        $pdf = new FPDFExtended('P', 'pt', 'A4');
        $pdf->SetTitle("Packing Voke {$order_number}");
        $w = $pdf->GetPageWidth() - 55;
        $rect_h = 4;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 16);
        $origin_x = $pdf->GetX();
        $pdf->Image($_SERVER['DOCUMENT_ROOT'] . '/assets/common/img/voke.png', $pdf->GetX(), $pdf->GetY(), 0, 0, "PNG");
        $pdf->Cell(0, 0, "Order Number {$order_number}", 0, 1, "R");
        $pdf->SetY($pdf->GetY() + 50);
        $pdf->SetFillColor(10, 10, 10);
        $pdf->Rect($pdf->GetX(), $pdf->GetY(), $w, $rect_h, "F");
        $pdf->SetY($pdf->GetY() + 20);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 0, "Shipped by", 0, 0, "L");
        $pdf->Cell(0, 0, "Deliver to", 0, 0, "R");
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0, iconv('UTF-8', 'windows-1252',$supplier->getName()) ?: '.', 0, 0, "L");
        $pdf->Cell(0, 0, iconv('UTF-8', 'windows-1252',$delivery->getContact()) ?: '.', 0, 0, "R");
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0, iconv('UTF-8', 'windows-1252',$supplierAddress->getAddress1()) ?: '.', 0, 0, "L");
        $pdf->Cell(0, 0, iconv('UTF-8', 'windows-1252',$delivery->getAddress1()) ?: '.', 0, 0, "R");
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$supplierAddress->getAddress2()) ?: '.', 0, 0, "L");
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$delivery->getAddress2()) ?: '.', 0, 0, "R");
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$supplierAddress->getAddress3()) ?: '.', 0, 0, "L");
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$delivery->getAddress3()) ?: '.', 0, 0, "R");
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$supplierAddress->getTown()) ?: '.', 0, 0, "L");
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$delivery->getTown()) ?: '.', 0, 0, "R");
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$supplierAddress->getCounty()) ?: '.', 0, 0, "L");
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$delivery->getCounty()) ?: '.', 0, 0, "R");
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$supplierAddress->getPostcode()) ?: '.', 0, 0, "L");
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$delivery->getPostcode()) ?: '.', 0, 0, "R");
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$supplierAddress->getCountryId()) ?: '.', 0, 0, "L");
        $pdf->Cell(0, 0,  iconv('UTF-8', 'windows-1252',$delivery->getCountryId()) ?: '.', 0, 0, "R");

        $VAT = SupplierWarehouses::getVAT($order->getWarehouseId(), $supplier->getId());
        if (!empty($VAT)) {
            $pdf->SetY($pdf->GetY() + 20);
            $pdf->Cell(0, 0, "VAT: " . $VAT, 0, 0, "L");
        }

        $pdf->SetY($pdf->GetY() + 20);
        $pdf->Rect($pdf->GetX(), $pdf->GetY(), $w, $rect_h, "F");

        $product_col_w = $w * 0.52;
        $barcode_col_w = $w * 0.38;
        $qty_col_w = $w * 0.1;
        $pdf->SetY($pdf->GetY() + 20);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell($product_col_w, 0, 'Product', 0, 0, "L");
        $pdf->Cell($barcode_col_w, 0, 'Batch/Expiry', 0, 0, "L");
        $pdf->Cell($qty_col_w, 0, 'Quantity', 0, 0, "L");
        $pdf->SetFont('Helvetica', '', 12);
        $cell_height = 0;
        foreach ($data['skus'] as $sku) {
            //Temporary solution to get batches for SKUs
            if (!$order->despatched()) {
                $sql = "
                    SELECT b.number, b.expired
                    FROM batches b
                        JOIN SKU_locations sl ON b.SKU_id = sl.SKU_id AND b.id = sl.batch_id
                    WHERE sl.order_id = " . (int)$order->getId() . "
                    AND sl.SKU_id = " . (int)$sku['id'];
                $batch_info = (new SafeMysql())->fetchAll("
                    SELECT b.number, b.expired
                    FROM batches b
                        JOIN SKU_locations sl ON b.SKU_id = sl.SKU_id AND b.id = sl.batch_id
                    WHERE sl.order_id = :order_id AND sl.SKU_id = :sku_id",
                    ['order_id' => (int)$order->getId(), 'sku_id' => (int)$sku['id']]);
            } else {
                $batch_info = [];
                $despatched_items = OrdersOriginalDespatched::getHandledItems($order);
                if (!empty($despatched_items))
                    foreach ($despatched_items as $despatched_item)
                        if ($sku['id'] == $despatched_item['SKU_id']) {
                            $batch = Batches::getById($despatched_item['batch_id']);
                            if ($batch !== null)
                                $batch_info[] = ['number' => $batch->getNumber(), 'expired' => $batch->getExpired(), 'quantity' => $despatched_item['quantity']];
                        }
            }
            $pdf->SetY($pdf->GetY() + 10 + $cell_height);
            $pdf->Rect($pdf->GetX(), $pdf->GetY(), $w, 1, "F");
            $pdf->SetY($pdf->GetY() + 10);
            $pdf->SetX($origin_x);
            $save_y = $pdf->GetY();
            $pdf->MultiCell($product_col_w, 10, iconv('UTF-8', 'windows-1252', $sku['name']), 0, "L");
            $pdf->SetXY($product_col_w + $origin_x, $save_y);
            if(!empty($batch_info)) {
                $txt = '';
                $qty = count($batch_info) > 1;
                foreach ($batch_info as $batch) {
                    $expiry = date('jS M Y', strtotime($batch['expired']));
                    $txt .= "{$batch['number']} ($expiry)" . ($qty ? (" ({$batch['quantity']} item" . ($batch['quantity'] > 1 ? 's' : '') . ")") : '') ."\n";
                }
                $pdf->MultiCell($barcode_col_w, 10, $txt, 0, "L");
            } else {
                Debug::putToLogFile("voke_packing_slip_empty_batch.dat","Voke order {$order->getId()} has no batch info");
                $pdf->MultiCell($barcode_col_w, 10, '-', 0, "L");
            }

            $pdf->SetXY($product_col_w + $barcode_col_w + $origin_x, $save_y);
            $pdf->MultiCell($qty_col_w, 10, $sku['quantity'], 0, "L");
            $cell_height = $pdf->GetMultiCellHeight($product_col_w, 10, iconv('UTF-8', 'windows-1252', $sku['name']), 0,
                "L");
        }
        $pdf->SetY($pdf->GetY() + 30);
        $pdf->SetFillColor(210,210,210);
        $pdf->Rect($pdf->GetX(), $pdf->GetY(), $w, 90, 'DF');

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetY($pdf->GetY() + 20);

        $pdf->Cell(0, 0,
            'Voke 0.45mg Inhaler contains nicotine and can be used to reduce, replace or stop smoking.',
            0, 0, "C");
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0,
            'Requires willpower. Always read the leaflet.',
            0, 0, "C");
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetY($pdf->GetY() + 20);

        $pdf->Cell(0, 0, iconv('UTF-8', 'windows-1252','Voke® and Voke® related materials, documents and instruction leaflets are the copyright of Kind Consumer'), 0, 0, "C");
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, iconv('UTF-8', 'windows-1252','Ltd, products may also be protected by one or more of the Patents, Trademarks or Registered Designs © Kind'), 0,
            0, "C");

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'Consumer Ltd 2019 Registered in England and Wales No 05760846', 0,
            0, "C");
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetY($pdf->GetY() + 40);
        $pdf->Cell(0, 0, 'Returns', 0, 0, "L");
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetY($pdf->GetY() + 20);
        $pdf->Cell(0, 0, '(a) Cancellation during the cooling-off period', 0, 0, "L");
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'You have certain legal rights to cancel and return your Voke order. For full details of your rights please review our', 0, 0, "L");
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'Terms and Conditions at www.voke.com. If you change your mind about your order and would like to cancel it, you', 0, 0, "L");
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'can do so without giving a reason, within 14 days of receiving the goods (the "cooling off period").', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 20);
        $pdf->Cell(0, 0, 'If you would like to cancel your order within 14 days please use the \'Cancellation Form\' on the Contact Us page of', 0, 0, "L");
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'www.voke.com. On receipt of the form, our team will contact you to arrange the return.', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0, 'Please note, all products must be returned in the condition in which you received them. This includes keeping all', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'protective cellophane wrappings in place. You will lose the right to return a product if you remove the cellophane', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'wrapping or any seals which need to be kept in place for health and hygiene reasons. For further details in relation', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'to your rights to return your order please refer to our Terms and Conditions at www.voke.com.', 0, 0, "L");

        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetY($pdf->GetY() + 20);
        $pdf->Cell(0, 0, '(b) Returning faulty products', 0, 0, "L");

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'If your Voke product is faulty, you can return it to us at any time and receive a refund or replacement of your', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'product.', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0, 'If you believe that your product is faulty and would like to return them to us, please first check our FAQ\'s at', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'www.voke.com. In our FAQ\'s you will find lots of information about how to get the best out of your Voke product,', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'including advice on how to use the product.', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 15);
        $pdf->Cell(0, 0, 'If you have checked our FAQ\'s and the product still appears faulty, please contact us at info@kindconsumer.com,', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'via our web chat icon, call 0800 046 9980 or using the the online form on the Contact Us page at www.voke.com,', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'to let us know that you would like to cancel and return your order.', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'Our team will take your details and provide a FREEPOST label to you containing the address to which you must', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'return your products. You do not need to pay for the cost of return of your product. Our team will also explain how', 0, 0, "L");

        $pdf->SetY($pdf->GetY() + 10);
        $pdf->Cell(0, 0, 'we can replace your product or provide you with a refund.', 0, 0, "L");

        $pdf->Output("I", "Packing slip " . $order_number, true);
    }

    private static function getGift($response)
    {
        if (empty($response['note_attributes']))
            return '';
        $note_attributes = $response['note_attributes'];
        if (!is_array($note_attributes))
            return '';
        $gift_data = [];
        foreach ($note_attributes as $note_attribute)
            $gift_data[$note_attribute['name']] = $note_attribute['value'];
        if (isset($gift_data['is_gift']) && isset($gift_data['gift_message']))
            return [$gift_data['is_gift'], $gift_data['gift_message']];
        return '';
    }

    /**
     * Retain.me is a 3rd party platform which prints Packing slip using it's own application
     * We only need to call API requests to them and we don't get any packing slips in response
     * //TODO separate 'order' and 'print' requests
     * @param array $data
     */
    public static function callRetainMe(array $data)
    {
        $test_order_api_login = RETAIN_ME_ORDER_LOGIN_TEST;
        $test_order_api_pwd = RETAIN_ME_ORDER_PWD_TEST;
        $test_print_api_login = RETAIN_ME_PRINT_LOGIN_TEST;
        $test_print_api_pwd = RETAIN_ME_PRINT_PWD_TEST;
        /** @var Order $order */
        $order = $data['order'];
        /** @var Supplier $supplier */
        $supplier = $data['supplier'];
        /** @var Delivery $delivery */
        $delivery = $data['delivery'];
        //1. Order data request
        $shipping_service_id = "collection_by_customer";
        $shipping_service_name = Shipping::COLLECTION_BY_CUSTOMER;
        $shipping_service = ShippingServices::getById($order->getShippingServicesId());
        if ($shipping_service) {
            $shipping_service_id = $shipping_service->getCode();
            $shipping_service_name = $shipping_service->getName();
        }
        $packages = OrdersModel::getPackages($order->getId());
        //we can get shipping info from the very first package
        $first_package = current($packages);
        $total_price = 0;
        $warehouse = Warehouses::getById($order->getWarehouseId());
        $currency = Currencies::getCurrency($warehouse->getCurrencyId());
        $products_data = [];
        $bundles = Bundles::getByOrderId($order->getId());
        if (!empty($bundles))
            foreach ($bundles as $bundle)
                $products_data[] = [
                    'product_id' => "bundle_" . $bundle['id'],
                    'name' => $bundle['name'],
                    'quantity' => 0,
                    'shipment_id' => empty($first_package['shipment_id']) ? 0 : $first_package['shipment_id'],
                ];
        foreach ($data['skus'] as $sku) {
            $total_price += $sku['value'];
            //SKUs should all have the same currency
            if (!empty($sku['market_currency']))
                $currency = $sku['market_currency'];
            $products_data[] = [
                'product_id' => $sku['id'],
                'name' => $sku['name'],
                'quantity' => $sku['quantity'],
                'price' => round($sku['value2'] / $sku['quantity'], 2),
                'shipment_id' => empty($first_package['shipment_id']) ? 0 : $first_package['shipment_id'],
                'custom_1' => $sku['customer_ref'],
            ];
        }
        $delivery_name = empty($delivery->getContact()) ? $delivery->getName() : $delivery->getContact();
        list($first_name, $last_name) = Str::getFirstLastName($delivery_name);
        $custom1 = $order->getRef2();
        if (empty($custom1))
            $custom1 = $order->getRef();
        $custom2 = '';
        $gift = '';
        if ($order->getTypeCreate() == Sources::SOURCE_SHOPIFY) {
            $order_original = OrdersOriginalRequest::getOriginalRequest($order->getId());
            if (!empty($order_original['response'])) {
                $response = json_decode($order_original['response'], true);
                if (!empty($response)) {
                    $customer = $response['customer'];
                    if (!empty($customer['orders_count']))
                        $custom2 = $customer['orders_count'];
                    $gift = self::getGift($response);
                }
            }
        }
        $shipment_data = [
            'shipment_id' => empty($first_package['shipment_id']) ? 0 : $first_package['shipment_id'],
            'shipping_method_id' => $shipping_service_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'address1' => $delivery->getAddress1(),
            'address2' => $delivery->getAddress2(),
            'city' => $delivery->getTown(),
            'country_code' => $delivery->getCountryId(),
            'province' => $delivery->getCounty(),
            'zip_postalcode' => $delivery->getPostcode(),
        ];
        if (!empty($gift)) {
            $shipment_data['is_gift'] = $gift[0];
            $shipment_data['gift_message'] = $gift[1];
        }
        $request_data = [
            [
                'order_number' => $order->getId(),
                'order_created_at' => $order->getCreateDate(),
                'total_price' => $total_price,
                'currency' => $currency,
                'shipping_methods' => [
                    [
                        'shipping_method_id' => $shipping_service_id,
                        'name' => $shipping_service_name,
                    ]
                ],
                'shipments' => [
                    $shipment_data
                ],
                'customer' => [
                    'customer_id' => $delivery->getId(),
                    'email' => $delivery->getEmail(),
                    'is_new' => (int)$custom2 === 1,
                ],
                'products' => $products_data,
                'custom_1' => $custom1,
            ]
        ];
        if (!empty($order->getShopId())) {
            $shop = Shops::getById($order->getShopId());
            if (!empty($shop))
                $request_data[0]['partner_id'] = $shop->getCredentials()['shop_url'];
        }
        if (!empty($custom2))
            $request_data[0]['custom_2'] = "$custom2";
        $test_orders_endpoint = "https://orders-beta.smartslip.io/api/v2/orders";
        $test_print_endpoint = "https://dc-beta.smartslip.io/api/v1/print";
        $live_orders_endpoint = "https://orders.smartslip.io/api/v2/orders";
        $live_print_endpoint = "https://dc.smartslip.io/api/v1/print";
        /** @var $credentials RetainMeCredentials */
        $credentials = SuppliersWarehouses::getRetainMeCredentials($order->getWarehouseId(), $supplier->getId());
        if (empty($credentials->getOrdersLogin()) || empty($credentials->getOrdersPwd())
            || ($credentials->getOrdersLogin() === $test_order_api_login && $credentials->getOrdersPwd() === $test_order_api_pwd)
            || !Environment::isProduction()) {
            $orders_endpoint = $test_orders_endpoint;
            $orders_login = $test_order_api_login;
            $orders_pwd = $test_order_api_pwd;
        } else {
            $orders_endpoint = $live_orders_endpoint;
            $orders_login = $credentials->getOrdersLogin();
            $orders_pwd = $credentials->getOrdersPwd();
        }
        if (empty($credentials->getPrintLogin()) || empty($credentials->getPrintPwd())
            || ($credentials->getPrintLogin() === $test_print_api_login && $credentials->getPrintPwd() === $test_print_api_pwd)
            || !Environment::isProduction()) {
            $print_endpoint = $test_print_endpoint;
            $print_login = $test_print_api_login;
            $print_pwd = $test_print_api_pwd;
        } else {
            $print_endpoint = $live_print_endpoint;
            $print_login = $credentials->getPrintLogin();
            $print_pwd = $credentials->getPrintPwd();
        }
        // Order request
        if(in_array('order', $data['requests'])) {
            $curl = new CURL($orders_endpoint, 'packing_slips_retain_me_order');
            $curl->setBaseAuth($orders_login, $orders_pwd);
            $curl->setHeader("Content-type", "application/json");
            $curl->setBody(json_encode($request_data));
            $result = $curl->exec();
            sleep(1);
            if ($curl->getResultCode() !== 200) {
                self::retainMeErrorReport($curl, $result);
                return false;
            }
            $decoded_response = json_decode($result, 1);
            if (empty($decoded_response) || empty($decoded_response['ValidOrderNumbers'])) {
                self::retainMeErrorReport($curl, $result);
                return false;
            }
            if (!in_array(strtoupper($order->getId()), array_map('strtoupper', $decoded_response['ValidOrderNumbers']))) {
                self::retainMeErrorReport($curl, $result);
                return false;
            }
        }
        //Print request
        if(in_array('print', $data['requests'])) {
            if (empty($data['source_id']))
                return false;
            $curl = new CURL($print_endpoint, 'packing_slips_retain_me_print');
            $curl->setBaseAuth($print_login, $print_pwd);
            $curl->setHeader("Content-type", "application/json");
            $curl->setBody(json_encode([[
                'order_number' => strtoupper($order->getId()),
                'source_id' => $data['source_id']
            ]]));
            $result = $curl->exec();
            if ($curl->getResultCode() !== 200 || empty($result)) {
                self::retainMeErrorReport($curl, $result);
                return false;
            }
            $result_arr = json_decode($result, 1);
            if ($result_arr['StatusCode'] != 200) {
                self::retainMeErrorReport($curl, $result);
                return false;
            }
        }
        return true;
    }

    /**
     * @param $curl CURL
     * @param $result string
     */
    private static function retainMeErrorReport($curl, $result)
    {
        $error_log = EXTERNAL_STORAGE . "/retainme_error_log";
        if (!is_dir($error_log))
            if (!mkdir($error_log) && !is_dir($error_log))
                Debug::sendDebugInfo("Cannot create {$error_log} directory", "");
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($error_log)));
        if (!$file_wrapper->isFile($error_log . "/retain_me_errors"))
            Debug::sendDebugInfo("Retain.me issues", "See log file " . $error_log . "/retain_me_errors");
        $data = var_export([
            $result,
            $curl->getUrl(),
            $curl->getResponse(),
            $curl->getResultCode(),
            $curl->getResultError(),
            $curl->getResultHeaders()
        ], 1);
        $file_string = stream_get_contents($file_wrapper->getStream($error_log . "/retain_me_errors"));
        $file_wrapper->writeFileString($error_log . "/retain_me_errors", $file_string . $data);
    }

    public static function requestRetainMeOrders()
    {
        $order_ids = RetainmeOrders::getOrders();
        if (empty($order_ids))
            return;
        $orders = [];
        foreach ($order_ids as $order_id) {
            $order = Orders::getById($order_id);
            if (empty($order)) {
                Debug::sendDebugInfo('requestRetainMeOrders', "Order #$order_id is not found", Debug::TEST);
                continue;
            }
            $supplier = Suppliers::getById($order->getSupplierId());
            if (empty($supplier)) {
                Debug::sendDebugInfo('requestRetainMeOrders', "Order #$order_id: supplier is not found", Debug::TEST);
                continue;
            }
            $delivery = DeliveriesModel::getById($order->getDeliveryId());
            if (empty($delivery)) {
                Debug::sendDebugInfo('requestRetainMeOrders', "Order #$order_id: delivery is not found", Debug::TEST);
                continue;
            }
            $data = [
                'order' => $order,
                'supplier' => $supplier,
                'delivery' => $delivery,
                'skus' => OrderSKUsModel::getSKUs($order->getId()),
                'requests' => ['order'],
                'source_id' => Printers::getDefault($order->getWarehouseId()),
            ];
            self::callRetainMe($data);
            $orders[] = $order_id;
        }
        RetainmeOrders::updateOrders($orders);
    }

    private static function drawGenericLogo(object $pdf, Supplier $supplier)
    {
        $logo = $supplier->getLogo();
        if (iconv_strlen($logo) > 0) {
            try {
                $logoPath = SuppliersModel::getLogoPath($logo);
                $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($logoPath)));
                if ($file_wrapper->isFile($logoPath)) {
                    $file_stream = $file_wrapper->getStream($logoPath);
                    $temp = tmpfile();
                    stream_copy_to_stream($file_stream, $temp);
                    $logoPath = stream_get_meta_data($temp)['uri'];
                    $maxSize = 150;
                    list($imageW, $imageH) = getimagesize($logoPath);
                    if ($imageW > $maxSize || $imageH > $maxSize) {
                        if ($imageH >= $imageW) {
                            $imageW = ($maxSize * $imageW) / $imageH;
                            $imageH = $maxSize;
                        } else {
                            $imageH = ($maxSize * $imageH) / $imageW;
                            $imageW = $maxSize;
                        }
                    }
                    $pdf->Image($logoPath, $pdf->GetX(), $pdf->GetY(), $imageW, $imageH, File::getImageType($logoPath));
                    return $imageH;
                }
                throw new \Exception('');
            } catch (\Exception $e) {
                $pdf->Cell(0, 50, $supplier->getName(), 0, 1, "L");
                return 80;
            }
        } else {
            $pdf->Cell(0, 50, $supplier->getName(), 0, 1, "L");
            return 80;
        }
    }
}
