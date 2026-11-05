<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ASN;
use app\core\entities\Batch;
use app\core\entities\Location;
use app\core\ErrorHandler;
use app\core\exceptions\BatchException;
use app\core\helpers\Arr;
use app\core\helpers\File;
use app\core\helpers\FPDFExtended;
use app\core\models\BarCode128;
use app\core\models\Batches;
use app\core\models\SKUPreffLocs;
use app\core\statical\Services;
use FPDF;

require_once (ROOT_PATH . VENDOR_PATH . '/FPDF/fpdf.php');

class GoodsIn
{
    public static function getArrivalMessageSKU($SKU_id, $warehouse_id)
    {
        $loc_sizes = [Location::PF => null, Location::PF . '_av' => 0, Location::ST => null, Location::ST . '_av' => 0];
        $places = SKUPreffLocs::getPlaces($warehouse_id, [$SKU_id]);
        $placesST = SKUPreffLocs::getPlacesST($warehouse_id, $SKU_id);
        foreach ($places as $key => $place)
            if ($place['type_id'] === Location::ST)
                $places[$key]['nums'] = $placesST[$place['size_id']];
        $sizes = SKUs::getSizes([$SKU_id]);
        $capacity = [];
        $tmp = json_decode($sizes[$SKU_id]['max_capacity'], true);
        if (!empty($tmp))
            $capacity = Arr::cleanEmptyVal($tmp);
        foreach ($places as $key => $place) {
            if (count($capacity) > 0 && !empty($capacity[$place['size_id']]))
                $loc_sizes[$place['type_id']] = (int) ($loc_sizes[$place['type_id']] ?? 0) + (int)($capacity[$place['size_id']] * $place['nums']);
            else
                $loc_sizes[$place['type_id']] = null;
        }
        $loc_sizes[Location::PF . '_av'] = (int)SKULocations::getAvailable($warehouse_id, $SKU_id);
        $loc_sizes[Location::ST . '_av'] = (int)SKULocations::getAvailable($warehouse_id, $SKU_id, Location::ST);

        $exist_PF = count(array_filter($places, function ($p) {
                return Location::isPF($p['type_id']);
            })) > 0;
        $exist_ST = count(array_filter($places, function ($p) {
                return $p['type_id'] === Location::ST;
            })) > 0;
        $cont_arrival_PF = ((!$exist_PF && is_null($loc_sizes[Location::PF]) && empty($capacity)) ? 1 : (!$exist_PF ? 2 :
                                            (is_null($loc_sizes[Location::PF]) ? 3
                                                : ((int)$loc_sizes[Location::PF] - $loc_sizes[Location::PF . '_av'] <= 0 ? 4
                                                    : 0))));
        $cont_arrival_ST = ((!$exist_ST && is_null($loc_sizes[Location::ST]) && empty($capacity)) ? 1 : (!$exist_ST ? 2 :
                                            (is_null($loc_sizes[Location::ST]) ? 3
                                                : ((int)$loc_sizes[Location::ST] - $loc_sizes[Location::ST . '_av'] <= 0 ? 4
                                                    : 0))));
        return 'Pick Face: <b>' . self::contArrival($cont_arrival_PF, $loc_sizes) . '</b><br/>' .
               'Storage: <b>' . self::contArrival($cont_arrival_ST, $loc_sizes, Location::ST) . '</b>';
    }

    public static function contArrival($cont_arrival, $loc_sizes, $type_id = Location::PF)
    {
        $msg_locs = 'Unassigned locations';
        $msg_m = 'Unconfigured measurements';
        switch ($cont_arrival) {
            case 0:
                $msg = "{$loc_sizes[$type_id . '_av']}</b> / <b>{$loc_sizes[$type_id]}";
                break;
            case 1:
                $msg = "<span class='text-danger'>{$msg_locs}; {$msg_m}</span>";
                break;
            case 2:
                $msg = "<span class='text-danger'>{$msg_locs}</span>";
                break;
            case 3:
                $msg = "<span class='text-danger'>{$msg_m}</span>";
                break;
            case 4:
                $msg = "<span class='text-danger'>{$loc_sizes[$type_id . '_av']}</span></b> / <b>{$loc_sizes[$type_id]}";
                break;
        }
        return $msg;
    }

    public static function getLastDo(ASN $ASN, $SKUs, $comments)
    {
        $lastSKUs = [];
        $lastDo = [];
        if ($ASN->nonCompliant()) {
            $ids = [];
            foreach ($SKUs as $SKU)
                $ids[] = $SKU['id'];
            foreach ($comments as $comment)
                if (substr($comment['comment'], 0, 9) === 'Partially' ||
                    substr($comment['comment'], 0, 3) === 'SKU' ||
                    substr($comment['comment'], 0, 8) === 'Adoption' ||
                    substr($comment['comment'], 0, 13) === 'Non-Compliant') {
                    list(,$num) = explode('#', $comment['comment']);
                    $lastDo[(int)$num] = $comment['comment'];
                }
            $lastSKUs = SKUs::getBySupplier($ASN->getSupplierId(), null, 1);
            $lastSKUs = array_filter($lastSKUs, function ($SKU) use ($ids, $lastDo) {
                return !(in_array($SKU['id'], $ids) || empty($lastDo[$SKU['id']]));
            });
        }
        return [$lastSKUs, $lastDo];
    }

    public static function createPdfASN(ASN $ASN, $SKUs)
    {
        if ($ASN->nonCompliant()) {
            $comments = ASNHistories::getByASN($ASN->getId(), $ASN->getSupplierId());
            list($lastSKUs, $lastDo) = self::getLastDo($ASN, $SKUs, $comments);
        }
        $pdf = new FPDFExtended('P', 'pt', 'A4');
        $pdf->SetTitle("ASN #{$ASN->getId()}");
        $W = $pdf->GetPageWidth() - 55;
        $h = 12;
        $pdf->AddPage();
        $pdf->SetFont('Courier', 'B', 16);
        $pdf->Cell(0,0, "ASN #{$ASN->getId()}", 0,0,'C');
        $pdf->Ln($h);
        $pdf->SetFont('Courier', 'B', 12);
        $pdf->Cell(0,0, 'Reference: ' . $ASN->getRef() ?: '-', 0,0,'C');
        $pdf->Ln($h);
        $pdf->Cell(0,0, 'Arrival Date: ' . date('jS F Y', $ASN->getArrivalDateInt()), 0,0,'C');
        $pdf->Ln($h);
        $pdf->Cell(0,0, "Status: {$ASN->getStatusNameAll()}", 0,0,'C');
        $pdf->Ln($h);
        if (!empty($ASN->getCarrier())) {
            $pdf->Cell(0,0, "Carrier: {$ASN->getCarrier()}", 0,0,'C');
            $pdf->Ln($h);
        }
        if (!empty($ASN->getTrackingNumber())) {
            $pdf->Cell(0,0, "Tracking Number: {$ASN->getTrackingNumber()}", 0,0,'C');
            $pdf->Ln($h);
        }
        $pdf->Ln($h);
        $pdf->SetFont('Courier', '', 10);
        if (count($ASN->getServices())) {
            $pdf->MultiCell($W,$h,'The customer has requested the following service(s) to be undertaken if this ASN is non-compliant:',0, 'J');
            $pdf->Ln($h);
            $i = 1;
            foreach ($ASN->getServices() as $key => $item) {
                $pdf->Cell(0,0, ($i++) . '. ' . (is_integer($item) ? Services::getService($key) : $item), 0,0,'L');
                $pdf->Ln($h);
            }
        }
        $pdf->SetFont('Courier', '', 12);
        $pdf->Ln($h);
        $pdf->Cell(0,0, "Expected SKUs:", 0,0);
        $pdf->Ln($h);
        $pdf->SetFont('Courier', 'B', 12);
        if ($ASN->getStatus() == 6) {
            $pdf->Cell($W*0.35,$h,'SKU',1);
            $pdf->Cell($W*0.22,$h,'Barcode',1);
            $pdf->Cell($W*0.13,$h,'Quantity',1);
            $pdf->Cell($W*0.3,$h,'Action',1);
        } else {
            $pdf->Cell($W*0.57,$h,'SKU',1);
            $pdf->Cell($W*0.3,$h,'Barcode',1);
            $pdf->Cell($W*0.13,$h,'Quantity',1);
        }
        $pdf->Ln();
        $pdf->SetFont('Courier', '', 12);
        $barcodes = [];
        $maxWidth = $W * ($ASN->getStatus() == 6 ? 0.22 : 0.3);
        foreach ($SKUs as $SKU) {
            foreach ($SKU['barcodes'] as $key => $barcode) {
                $barcodes[$SKU['id']][$key] = new BarCode128($barcode,85, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf', 8);
                if ($maxWidth < $barcodes[$SKU['id']][$key]->Width())
                    $maxWidth = $barcodes[$SKU['id']][$key]->Width();
            }
        }
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $koef = $W * ($ASN->getStatus() == 6 ? 0.22 : 0.3) / $maxWidth;
        $getHeight = function ($barcodes) {
            return array_sum(array_map(function ($item) {
                return $item->Height();
            }, $barcodes));
        };

        $pdf->SetAutoPageBreak(true, 10);
        foreach ($SKUs as $SKU) {
            $SKU_title = "{$SKU['name']} ({$SKU['id']})" ;
            if(!empty($SKU['batch_number'])) {
                if ($ASN->getOrderId()) {
                    $batch_number_data = json_decode($SKU['batch_number'], true);
                    //TODO hot fix запрос к базе в цикле, переделать
                    if ($batch_number_data && isset($batch_number_data['batch']) && $batch = Batches::getById($batch_number_data['batch'])) {
                        $SKU_title .= "\nBatch No. " . $batch->getNumber() . "\nExpired " . (new \DateTime($batch->getExpired()))->format('Y-m-d');
                    }
                } else {
                    $SKU_title .= "\nBatch No. {$SKU['batch_number']}\nExpired " . (new \DateTime($SKU['expired']))->format('Y-m-d');
                }
            }
            $pdf->SetXY($x, $y);
            if ($ASN->getStatus() == 6) {
                $maxY = max([
                    $pdf->GetMultiCellHeight($W*0.35, $h, $SKU_title),
                    $koef * $getHeight($barcodes[$SKU['id']]),
                    $pdf->GetMultiCellHeight($W*0.13, $h, $SKU['quantity']),
                    $pdf->GetMultiCellHeight($W*0.3, $h, $lastDo[$SKU['id']]),
                ]);
                if (($y + $maxY) > ($pdf->GetPageHeight() - 25)) {
                    $pdf->AddPage();
                    $pdf->SetY(20);
                    $y = $pdf->GetY();
                }
                $pdf->MultiCell($W*0.35,$h, $SKU_title,0, 'L');
                $pdf->SetXY($x + $W*0.35, $y);
                $height = 0;
                foreach ($barcodes[$SKU['id']] as $barcode) {
                    $pdf->Image($barcode->getImage(), $x + $W*0.35, $y + 1 + $height, $koef * $barcode->Width(), 0, 'PNG');
                    $height += $barcode->Height()*$koef;
                }
                $pdf->SetXY($x + $W*0.57, $y);
                $pdf->MultiCell($W*0.13,$h,$SKU['quantity'],0, 'L');
                $pdf->SetXY($x + $W*0.7, $y);
                $pdf->MultiCell($W*0.3,$h,$lastDo[$SKU['id']],0, 'L');
            } else {
                $maxY = max([
                    $pdf->GetMultiCellHeight($W*0.57, $h, $SKU_title),
                    $koef * $getHeight($barcodes[$SKU['id']]),
                    $pdf->GetMultiCellHeight($W*0.13, $h, $SKU['quantity']),
                ]);
                if (($y + $maxY) >= ($pdf->GetPageHeight() - 25)) {
                    $pdf->AddPage();
                    $pdf->SetY(20);
                    $y = $pdf->GetY();
                }
                $pdf->MultiCell($W*0.57,$h, $SKU_title,0, 'L', false);
                $pdf->SetXY($x + $W*0.57, $y);
                $height = 0;
                foreach ($barcodes[$SKU['id']] as $barcode) {
                    $pdf->Image($barcode->getImage(), $x + $W*0.57, $y + 1 + $height, $koef * $barcode->Width(), 0, 'PNG');
                    $height += $barcode->Height()*$koef;
                }
                $pdf->SetXY($x + $W*0.87, $y);
                $pdf->MultiCell($W*0.13,$h, $SKU['quantity'],0, 'L');
            }
            $pdf->SetXY($x, $y);
            if ($ASN->getStatus() == 6) {
                $pdf->Cell($W*0.35,$maxY+2,'',1);
                $pdf->Cell($W*0.22,$maxY+2,'',1);
                $pdf->Cell($W*0.13,$maxY+2,'',1);
                $pdf->Cell($W*0.3,$maxY+2,'',1);
            } else {
                $pdf->Cell($W*0.57, $maxY+2,'',1);
                $pdf->Cell($W*0.3, $maxY+2,'',1);
                $pdf->Cell($W*0.13, $maxY+2,'',1);
            }
            $pdf->Ln();
            $pdf->SetXY($x, $y);
            $y += $maxY + 2;
        }
        if ($ASN->getStatus() == 6 && !empty($lastSKUs)) {
            $barcodes = [];
            $maxWidth = 0;
            $pdf->Ln();
            $pdf->Cell(0,20, "Unexpected SKUs:", 0, 0);
            $pdf->Ln();
            foreach ($lastSKUs as $SKU) {
                $tmp = explode(', ', $SKU['barcode']);
                foreach ($tmp as $key => $barcode) {
                    $barcodes[$SKU['id']][$key] = new BarCode128($SKU['barcode'], 85, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf', 8);
                    if ($maxWidth < $barcodes[$SKU['id']][$key]->Width())
                        $maxWidth = $barcodes[$SKU['id']][$key]->Width();
                }
            }
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $koef = $W * 0.22 / $maxWidth;
            foreach ($lastSKUs as $SKU) {
                $SKU_title = "{$SKU['name']} ({$SKU['id']})";
                if(!empty($SKU['batch_number'])) {
                    if ($ASN->getOrderId()) {
                        $batch_number_data = json_decode($SKU['batch_number'], true);
                        //TODO hot fix запрос к базе в цикле, переделать
                        if ($batch_number_data && isset($batch_number_data['batch']) && $batch = Batches::getById($batch_number_data['batch'])) {
                            $SKU_title .= "\nBatch No. " . $batch->getNumber() . "\nExpired " . (new \DateTime($batch->getExpired()))->format('Y-m-d');
                        }
                    } else {
                        $SKU_title .= "\nBatch No. {$SKU['batch_number']}\nExpired " . (new \DateTime($SKU['expired']))->format('Y-m-d');
                    }
                }
                $pdf->SetXY($x, $y);
                $maxY = max([
                    $pdf->GetMultiCellHeight($W*0.35, $h, $SKU_title),
                    $koef * $getHeight($barcodes[$SKU['id']]),
                    $pdf->GetMultiCellHeight($W*0.3, $h, $lastDo[$SKU['id']]),
                ]);
                if (($y + $maxY) > ($pdf->GetPageHeight() - 25)) {
                    $pdf->AddPage();
                    $y = $pdf->GetY();
                }
                $pdf->MultiCell($W*0.35,$h, $SKU_title,0, 'L');
                $pdf->SetXY($x + $W*0.35, $y);
                $height = 0;
                foreach ($barcodes[$SKU['id']] as $barcode) {
                    $pdf->Image($barcode->getImage(), $x + $W*0.35, $y + 1 + $height, $koef * $barcode->Width(), 0, 'PNG');
                    $height += $barcode->Height()*$koef;
                }
                $pdf->SetXY($x + $W*0.7, $y);
                $pdf->MultiCell($W*0.3,$h,$lastDo[$SKU['id']],0, 'L');
                $pdf->SetXY($x, $y);
                $pdf->Cell($W*0.35,$maxY+2,'',1);
                $pdf->Cell($W*0.22,$maxY+2,'',1);
                $pdf->Cell($W*0.13,$maxY+2,'',1);
                $pdf->Cell($W*0.3,$maxY+2,'',1);
                $pdf->Ln();
                $pdf->SetXY($x, $y);
                $y += $maxY + 2;
            }
        }
        $pdf->Output();
    }

    public static function createPdfPutaway($SKUs)
    {
        $pdf = new FPDFExtended('P', 'pt', 'A4');
        $pdf->SetTitle('Putaway Queue');
        $pdf->W = $pdf->GetPageWidth() - 55;
        $pdf->H = 12;
        $pdf->AddPage();
        $pdf->SetFont('Courier', 'B', 12);
        $pdf->Cell(0,0, 'Putaway Queue', 0,0,'C');
        $pdf->Ln($pdf->H);
        $pdf->addLines();
        self::createPdfPutawayTHeader($pdf);
        $pdf->SetFont('Courier', '', 10);
        $pdf->SetTopMargin(0);
        foreach ($SKUs as $SKU)
            self::createPdfPutawayRow($pdf, $SKU);
        $pdf->Output();
    }

    public static function createPdfPutawayTHeader(FPDFExtended &$pdf)
    {
        $pdf->SetFont('Courier', 'B', 10);
        $pdf->Cell($pdf->W*0.57, $pdf->H,'SKU / Location',1);
        $pdf->Cell($pdf->W*0.3, $pdf->H,'Barcode',1);
        $pdf->Cell($pdf->W*0.13, $pdf->H,'Quantity',1);
        $pdf->Ln();
        $pdf->addLines();
    }

    public static function createPdfPutawayNextPage(FPDFExtended &$pdf)
    {
        $pdf->AddPage();
        $pdf->Ln($pdf->H);
        self::createPdfPutawayTHeader($pdf);
        $pdf->SetFont('Courier', '', 10);
        $pdf->SetTopMargin(0);
    }

    public static function createPdfPutawayRow(FPDFExtended &$pdf, $SKU)
    {
        $name = mb_strimwidth($SKU['name'], 0, 72, "...");
        $SKUname = iconv_strlen($name) > 41 ? $name . " ({$SKU['SKU_id']})" : $name . "\n({$SKU['SKU_id']})";
        if (!$pdf->addLines(4))
            self::createPdfPutawayNextPage($pdf);
        $maxY = 0;
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($pdf->W*0.57, $pdf->H,$SKUname . (empty($SKU['batch_id']) ? "\n" : ("\n" . Batch::toBatchInfo($SKU['number'], $SKU['expired'], false))) . "\n{$SKU['loc_name']}",0, 'L');
        if ($maxY < $pdf->GetY() - $y)
            $maxY = $pdf->GetY() - $y;
        $pdf->SetXY($x + $pdf->W*0.57, $y);
        $pdf->MultiCell($pdf->W*0.3, $pdf->H,$SKU['barcode'],0);
        if ($maxY < $pdf->GetY() - $y)
            $maxY = $pdf->GetY() - $y;
        $pdf->SetXY($x + $pdf->W*0.87, $y);
        $pdf->MultiCell($pdf->W*0.13, $pdf->H,$SKU['qty'],0, 'C');
        if ($maxY < $pdf->GetY() - $y)
            $maxY = $pdf->GetY() - $y;
        $pdf->SetXY($x, $y);
        $pdf->Cell($pdf->W*0.57, $maxY,'',1);
        $pdf->Cell($pdf->W*0.3, $maxY,'',1);
        $pdf->Cell($pdf->W*0.13, $maxY,'',1);
        $pdf->Ln();
    }

    public static function createPdfManifest(ASN $ASN, $SKUs)
    {
        $places = SKUPreffLocs::getPlaces($ASN->getWarehouseId(), array_keys(Arr::map($SKUs, 'id', 'quantity')));
        foreach ($places as $place)
            if (Location::isPF($place['type_id']))
                $SKUs[$place['SKU_id']][Location::PF] = 'Y';
        $pdf = new FPDFExtended('P', 'pt', 'A4');
        $pdf->SetTitle("ASN #{$ASN->getId()}");
        $W = $pdf->GetPageWidth() - 55;
        $h = 12;
        $pdf->AddPage();
        $pdf->SetFont('Courier', 'B', 16);
        $pdf->Cell(0,0, "ASN #{$ASN->getId()}", 0,0,'C');
        $pdf->Ln($h);
        $pdf->SetFont('Courier', 'B', 12);
        if (!empty($ASN->getRef())) {
            $pdf->Cell(0, 0, 'Reference: ' . $ASN->getRef(), 0, 0, 'C');
            $pdf->Ln($h);
        }
        $pdf->Cell(0,0, 'Arrival Date: ' . date('jS F Y', $ASN->getArrivalDateInt()), 0,0,'C');
        $pdf->Ln($h);
        if (!empty($ASN->getCarrier())) {
            $pdf->Cell(0,0, "Carrier: {$ASN->getCarrier()}", 0,0,'C');
            $pdf->Ln($h);
        }
        if (!empty($ASN->getTrackingNumber())) {
            $pdf->Cell(0,0, "Tracking Number: {$ASN->getTrackingNumber()}", 0,0,'C');
            $pdf->Ln($h);
        }
        $pdf->Ln($h);
        $pdf->SetFont('Courier', '', 10);
        if (count($ASN->getServices())) {
            $pdf->MultiCell($W,$h,'The customer has requested the following service(s) to be undertaken if this ASN is non-compliant:',0, 'J');
            $pdf->Ln($h);
            $i = 1;
            foreach ($ASN->getServices() as $key => $item) {
                $pdf->Cell(0,0, ($i++) . '. ' . (is_integer($item) ? Services::getService($key) : $item), 0,0,'L');
                $pdf->Ln($h);
            }
        }
        $pdf->SetFont('Courier', '', 12);
        $pdf->Ln($h);
        $pdf->Cell(0,0, "Expected SKUs:", 0,0);
        $pdf->Ln($h);
        $pdf->SetFont('Courier', 'B', 12);
        $pdf->Cell($W*0.45,$h,'SKU',1);
        $pdf->Cell($W*0.25,$h,'Barcode',1);
        $pdf->Cell($W*0.12,$h,'Quantity',1);
        $pdf->Cell($W*0.09,$h,'Dims',1);
        $pdf->Cell($W*0.09,$h,Location::PF,1);
        $pdf->Ln();
        $pdf->SetFont('Courier', '', 12);
        $pdf->SetTopMargin(25);
        foreach ($SKUs as $SKU) {
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->SetXY($x, $y);
            [$barcodes, $width, $height] = self::getBarcodes($SKU['barcodes']);
            $bW = min($width, $W*0.25);
            $koeff = $bW / $width;
            $skuTitle = "{$SKU['name']} ({$SKU['id']})" ;
            if(!empty($SKU['batch_number']))
                $skuTitle .= "\nBatch No. {$SKU['batch_number']}\nExpired " . (new \DateTime($SKU['expired']))->format('Y-m-d');
            $maxY = max([
                $pdf->GetMultiCellHeight($W*0.45,$h,$skuTitle),
                ($bW * $height) / $width,
                $pdf->GetMultiCellHeight($W*0.12,$h,$SKU['quantity']),
                $pdf->GetMultiCellHeight($W*0.09,$h,$SKU['dims']),
                $pdf->GetMultiCellHeight($W*0.09,$h,$SKU[Location::PF]),
            ]);
            if (($y + $maxY + 20) > $pdf->GetPageHeight() - 40) {
                $pdf->AddPage();
                $y = $pdf->GetY();
                $pdf->SetXY($x, $y);
            }
            $pdf->MultiCell($W*0.45,$h,$skuTitle,0, 'L');
            $pdf->SetXY($x + $W*0.45, $y);
            $y_barcodes = $y;
            foreach ($barcodes as $barcode) {
                $pdf->Image($barcode->getImage(), $x + $W*0.45, $y_barcodes, (int)($barcode->Width() * $koeff), 0, 'PNG');
                $y_barcodes += (int)($barcode->Height() * $koeff);
            }
            $pdf->SetXY($x + $W*0.7, $y);
            $pdf->MultiCell($W*0.12,$h,$SKU['quantity'],0, 'C');
            $pdf->SetXY($x + $W*0.82, $y);
            $pdf->MultiCell($W*0.09,$h,$SKU['dims'],0, 'C');
            $pdf->SetXY($x + $W*0.91, $y);
            $pdf->MultiCell($W*0.09,$h,$SKU[Location::PF],0, 'C');
            $pdf->SetXY($x, $y);
            $pdf->Cell($W*0.45,$maxY,'',1);
            $pdf->Cell($W*0.25,$maxY,'',1);
            $pdf->Cell($W*0.12,$maxY,'',1);
            $pdf->Cell($W*0.09,$maxY,'',1);
            $pdf->Cell($W*0.09,$maxY,'',1);
            $pdf->Ln();
        }
        $pdf->Output();
    }

    private static function getBarcodes(array $barcodes_txt): array
    {
        $width = $height = 0;
        $barcodes = [];
        foreach ($barcodes_txt as $barcode_txt) {
            $barcode = new BarCode128($barcode_txt,100, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf', 9);
            if ($width < $barcode->Width())
                $width = $barcode->Width();
            $height += $barcode->Height();
            $barcodes[] = $barcode;
        }
        return [$barcodes, $width, $height];
    }

    public static function createPdfNewBarcode($ASN, $SKU)
    {
        $barcode = new BarCode128($SKU, 120, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf');
        $pdf = new FPDF('P', 'pt', 'asn');
        $title = is_null($ASN) ? 'No ASN' : "ASN #{$ASN->getId()}";
        $pdf->SetTitle($title);
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 26);
        $pdf->Cell(0,80, $title, 0,0,'C');
        $pdf->Ln();
        $pdf->SetFontSize(20);
        if (!is_null($ASN)) {
            $supplier = Suppliers::getById($ASN->getSupplierId());
            $pdf->MultiCell(0,22, $supplier->getName() . ' (' . $supplier->getId() . ')', 0,'C');
        }
        $mar = ($pdf->GetPageWidth() - $barcode->Width()) / 2;
        $pdf->Image($barcode->getImage(), $mar, 250, $barcode->Width(), $barcode->Height(), 'PNG');
        $pdf->Output();
    }

    public static function setPutaway($warehouse_id, $ASN_id, $SKU_id, $location_id, $quan, $batch_id, $user_id, $is_PA)
    {
        if (!$is_PA) {
            $max = SKUs::getMaxCapacity($SKU_id, $location_id);
            $put = SKULocations::getQuantity($SKU_id, $location_id, $batch_id, true);
            if ($quan > $max - $put)
                return false;
        }
        if (!SKULocations::addPutaway($warehouse_id, $SKU_id, $location_id, $quan, $batch_id))
            return false;
        ASNTemporaries::addPutaway($ASN_id, $SKU_id, $quan);
        $ASN = ASNs::getById($ASN_id);
        if ($ASN->new()) {
            $errors = ASNs::arrived($ASN, $user_id);
            if ($errors !== true)
                return $errors;
            $move_id = Movements_::setASN($SKU_id, $quan, $ASN_id);
            SKUHistories::addASN($SKU_id, $quan, $move_id, $ASN_id, $warehouse_id);
        }
        if ($ASN->arrived()) {
            $errors = ASNs::putaway($ASN, $user_id);
            if ($errors !== true)
                return $errors;
        }
        $errors = ASNHistories::putsASN($SKU_id, $ASN_id, $quan, 1, $user_id);
        if ($errors !== true)
            return $errors;
        $move_id = Movements_::setPutaway($location_id, $SKU_id, $quan, $batch_id, $user_id);
        SKUHistories::addPutaway($location_id, $SKU_id, $quan, $move_id, $user_id, $warehouse_id);
        SKUHistories::changeStockOneLocation($location_id, $SKU_id, $warehouse_id);
        return true;
    }

    public static function loadFile($files)
    {
        switch ($files['error']) {
            case 0:
                break;
            case 4:
                return [true, ''];
            default:
                return [false, ErrorHandler::errorFileUpload($files['error'])];
        }
        if (empty($files['name']))
            return [true, ''];
        // SKU photo: accept raster images only and store under a server-generated name (no user-supplied name/extension)
        if (!File::isRasterImage($files['tmp_name']))
            return [false, 'The file is not an image'];
        $photo = SKUs::savePhoto($files['tmp_name']);
        if ($photo === false)
            return [false, 'Access denied'];
        return [true, $photo];
    }

    public static function prepareSKUs($barcodes, $batches_num, $batches_exp, $quantity)
    {
        $SKUs = $errors = [];
        foreach ($quantity as $id => $qty) {
            $batch = $batches_num[$id] ?? '';
            $expired = $batches_exp[$id] ?? '';
            $barcode = $barcodes[$id] ?? '';
            $SKUs[$id] = ['name' => '', 'quantity' => $qty, 'batch_number' => $batch, 'batch_expired' => $expired];
            if (empty($qty) || $qty < 0) {
                $errors[]['message'] = "SKU $barcode: quantity must be greater than 0";
                continue;
            }
            if (empty($batch) && !empty($expired)) {
                $errors[]['message'] = "SKU $barcode: batch number cannot be empty when expiry filled up";
                continue;
            }
            if (!empty($batch) && (empty($expired) || (strtotime($expired) <= time()))) {
                $errors[]['message'] = "SKU $barcode: batch expiry cannot be empty when number filled up";
                continue;
            }
            if (empty($batch) && empty($expired))
                continue;
            $batch_info = Batches::getBatchBySKUAndNumber($id, $batch);
            if (empty($batch_info))
                try {
                    Batches::addBatch($batch, $id, $expired);
                } catch (BatchException $e) {
                    $errors[]['message'] = "SKU $barcode: {$e->getMessage()}";
                }
            elseif ((new \DateTime($batch_info['expired']))->format('Y-m-d') != (new \DateTime($expired))->format('Y-m-d'))
                $errors[]['message'] = "SKU $barcode: batch '$batch' already exists with a different expiry date";
        }
        return [$SKUs, $errors];
    }

    /**
     * @param  ASN  $ASN
     * @param  Location  $loc
     * @param $user_id
     * @param $warehouse_label
     * @return array|bool
     */
    public static function noVerify(ASN $ASN, Location $loc, $user_id, $warehouse_label)
    {
        $errors = [];
        $SKUs = ASNSKUs::getByASNId($ASN->getId());
        if (empty($SKUs)) {
            $errors[]['message'] = 'SKUs not found';
            return $errors;
        }
        if($loc->getTypeId() === Location::ST) {
            $loc_size_id = $loc->getSizeId();
            $sizes = SKUs::getSizes(array_map(function($SKU) {
                return $SKU['id'];
            }, $SKUs));
            $SKU_unmeasurements = [];
            foreach ($sizes as $SKU_id => $size) {
                $tmp = json_decode($size['max_capacity'], true);
                $capacity = Arr::cleanEmptyVal($tmp);
                if (empty($capacity[$loc_size_id]))
                    $SKU_unmeasurements[] = $size['barcode'];
            }
            if (!empty($SKU_unmeasurements)) {
                $errors[]['message'] = 'SKU' . (count($SKU_unmeasurements) > 1 ? 's ' : ' ') .implode(', ', $SKU_unmeasurements).
                                            ' do not have measurements for selected location';
                return $errors;
            }
            try {
                $fullness = Locations::getFullnessPercent($loc);
            } catch (\Exception $e) {
                $errors[]['message'] = $e->getMessage();
                return $errors;
            }
            $fullness += Locations::calcFullnessPercentForASN($loc, $ASN->getId());
            if ($fullness > 100) {
                $errors[]['message'] = 'The location does not fit the given quantity of SKUs';
                return $errors;
            }
            foreach (SKULocations::getSKUsFromLocation($loc->getId()) as $SKU_location) {
                $SKU_arr = array_filter($SKUs, function($SKU) use ($SKU_location) {
                    return $SKU['id'] == $SKU_location['SKU_id'];
                });
                if (!empty($SKU_arr))
                    foreach ($SKU_arr as $SKU_same)
                        if ($SKU_location['number'] != $SKU_same['batch_number']) {
                            $errors[]['message'] = 'Not possible to mix batches';
                            return $errors;
                        }
            }
        }

        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        foreach ($SKUs as $SKU) {
            if (!SKULocations::addPutaway(
                $loc->getWarehouse(),
                $SKU['id'],
                $loc->getId(),
                $SKU['quantity'],
                Batches::getBatchBySKUAndNumber($SKU['id'], $SKU['batch_number'])['id'] ?? null)
            ) {
                $mysql->rollback();
                $errors[]['message'] = 'Putaway is impossible';
                return $errors;
            }
            $mysql->execute("UPDATE ASN_SKUs
                            SET accepted_quantity = :quantity
                            WHERE ASN_id = :ASN_id AND SKU_id = :SKU_id", [
                'quantity' => $SKU['quantity'],
                'ASN_id' => $ASN->getId(),
                'SKU_id' => $SKU['id'],
            ]);
            $move_id = Movements_::setNoVerify($loc->getId(), $SKU['id'], $SKU['quantity'], $user_id);
            SKUHistories::addNoVerify($loc->getId(), $SKU['id'], $SKU['quantity'], $move_id, $user_id, $loc->getWarehouse());
            $errors = ASNHistories::noVerifyASN($ASN->getId(), $user_id, $SKU['id'], $SKU['quantity'], $loc->getLocation($warehouse_label));
            if ($errors !== true) {
                $mysql->rollback();
                return $errors;
            };
            SKUHistories::changeStockOneLocation($loc->getId(), $SKU['id'], $loc->getWarehouse());
        }

        $errors = ASNs::completed($ASN, ASN::NO_VERIFIED, $user_id);
        if ($errors !== true) {
            $mysql->rollback();
            return $errors;
        }
//        Queue::addJobToSetInventoryLevel(['asn_id' => $ASN->getId()]);
        ShopInventoryQueues::addASN($ASN->getId());
        $mysql->commit();
        return $errors;
    }

    public static function editSKUs(ASN $ASN, $quantities, $warehouse_virtual, $user_id)
    {
        $nums = count($quantities);
        foreach ($quantities as $SKU_id => $quantity)
            if ((int)$quantity <= 0)
                unset($quantities[$SKU_id]);
            else
                $quantities[$SKU_id] = (int)$quantity;
        if (count($quantities) != $nums)
            return [false, 'Number of items in the list of SKU is different from the original'];
        $old_quantities = Arr::maps(ASNSKUs::getByASNId($ASN->getId()), 'id', ['name', 'quantity']);
        $update_SKUs = [];
        foreach ($quantities as $SKU_id => $quantity) {
            if ($quantity == $old_quantities[$SKU_id]['quantity'])
                continue;
            if (empty($old_quantities[$SKU_id]))
                return [false, "SKUs #$SKU_id is not found in ASN"];
            $update_SKUs[$SKU_id] = ['new_quantity' => $quantity] + $old_quantities[$SKU_id];
        }
        if (empty($update_SKUs))
            return [false, "Change is not found"];
        $errors = ASNs::updated($ASN, $update_SKUs, $warehouse_virtual, $user_id);
        if ($errors !== true)
            return [false, $errors];
        return [true, ''];
    }
}
