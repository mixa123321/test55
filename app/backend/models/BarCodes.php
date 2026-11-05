<?php

namespace app\backend\models;

use app\core\models\BarCode128;
use FPDF;

class BarCodes extends BarCode128
{
    public static function showBarCodeLabels(FPDF $pdf, $barcode, $name, $qty, $h, $w, $w0, $h0, $m, $n)
    {
        if ($barcode == null || $m < 1 || $n < 1)
            return;
        $W = (210 - 2*$w - ($m - 1)*$w0)/$m;
        $H = (297 - 2*$h - ($n - 1)*$h0)/$n;
        $k = min($W/$barcode->Width()*0.95,$H/$barcode->Height()*0.75);
        $Wi = $barcode->Width()*$k;
        $Hi = $barcode->Height()*$k;
        $dW = ($W - $Wi)/2;
        $dH = ($H - $Hi)/2;
        $num = 0;
        $pdf->SetFont('Courier', 'B', 10);
        while ($num < $qty) {
            for ($i = 0; $i < $n; $i++)
                for ($j = 0; $j < $m; $j++) {
                    $pdf->SetXY($w + $j*($W + $w0) + $dW*0.5, $h + $i*($H + $h0) + $dH*0.4);
                    $pdf->MultiCell($Wi + $dW, $Hi * 0.15, $name, 0,'C');
                    $pdf->Image($barcode->getImage(), $w + $j*($W + $w0) + $dW, $h + $i*($H + $h0) + $dH + $Hi*0.18, $Wi, $Hi, 'PNG');
                    $num++;
                    if ($num == $qty)
                        return;
                }
            $pdf->AddPage();
        }
    }

    public static function showBarCodesTemplate(FPDF $pdf, $barcodes, $h, $w, $w0, $h0, $m, $n)
    {
        if ($barcodes == null || count($barcodes) < 1 || $m < 1 || $n < 1)
            return;
        $W = (210 - 2*$w - ($m - 1)*$w0)/$m;
        $H = (297 - 2*$h - ($n - 1)*$h0)/$n;
        $maxW = $maxH = 0;
        foreach ($barcodes as $barcode) {
            if ($barcode->Width() > $maxW)
                $maxW = $barcode->Width();
            if ($barcode->Height() > $maxH)
                $maxH = $barcode->Height();
        }
        $k = min($W/$maxW,$H/$maxH)*0.95;
        $num = 0;
        while ($num < count($barcodes)) {
            for ($i = 0; $i < $n; $i++) {
                for ($j = 0; $j < $m; $j++) {
                    $Wi = $barcodes[$num]->Width()*$k;
                    $Hi = $barcodes[$num]->Height()*$k;
                    $dW = ($W - $Wi)/2;
                    $dH = ($H - $Hi)/2;
                    $pdf->Image($barcodes[$num]->getImage(), $w + $j*($W + $w0) + $dW, $h + $i*($H + $h0) + $dH, $Wi, $Hi, 'PNG');
//                    $pdf->Rect($w + $j*($W + $w0), $h + $i*($H + $h0), $W, $H);
                    $num++;
                    if ($num == count($barcodes))
                        return;
                }
            }
            $pdf->AddPage();
        }
    }

    public static function showBarCodes($pdf, $SKUs, $num, $margin = 20)
    {
        if (empty($SKUs) || $num < 1)
            return;
        $w = $h = 0;
        $barcodes = [];
        foreach ($SKUs as $SKU) {
            foreach ($SKU['barcodes'] as $barcode) {
                $barcode = new BarCode128($barcode, 100, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf');
                $barcodes[$SKU['id']][] = ['image' => $barcode->getImage(), 'width' => $barcode->Width()];
                $b_w = $barcode->Width();
                $b_h = $barcode->Height();
                if ($b_w > $w) {
                    $w = $b_w;
                    $h = $b_h;
                }
            }
        }
        $W = (int)(($pdf->GetPageWidth() - 2 * $margin) / $num);
        $koefW = $W / $w;
        $H = (int)($W * $h / $w);
        $M = (int)(($pdf->GetPageHeight() - 2 * $margin) / $H);
        $size = $M * $num;
        $Nb = 0;
        for($K = 0; $K < count($SKUs); $K++) {
            foreach ($barcodes[$SKUs[$K]['id']] ?? [] as $barcode) {
                $i = (int)($Nb % $size / $num);
                $j = $Nb % $num;
                $pdf->Image($barcode['image'], $margin + $j * $W, $margin + $i * $H, $koefW * $barcode['width'], $H, 'PNG');
                $Nb++;
                if ($Nb % $size == 0) {
                    for($l = 0; $l < $M - 1; $l++)
                        $pdf->Line($margin, $margin + ($l + 1) * $H, $pdf->GetPageWidth() - $margin, $margin + ($l + 1) * $H);
                    for($l = 0; $l < $num - 1; $l++)
                        $pdf->Line($margin + ($l + 1) * $W, $margin, $margin + ($l + 1) * $W, $margin + $M * $H);
                    $pdf->AddPage();
                }
            }
        }
        $N = ceil((count($SKUs) % $size) / $num);
        for($l = 0; $l < $N - 1; $l++)
            $pdf->Line($margin, $margin + ($l + 1) * $H, $pdf->GetPageWidth() - $margin, $margin + ($l + 1) * $H);
        for($l = 0; $l < $num - 1; $l++)
            $pdf->Line($margin + ($l + 1) * $W, $margin, $margin + ($l + 1) * $W, $margin + $N * $H);
    }
}
