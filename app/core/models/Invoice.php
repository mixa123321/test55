<?php

namespace app\core\models;

use app\backend\models\InvoiceBillingSuppliers;
use app\backend\models\InvoiceBillingCharges;
use app\core\entities\Charge;
use app\core\helpers\File;
use app\core\helpers\FPDFExtendedUTF8;
use app\core\statical\Currencies;
use NumberFormatter;

class Invoice
{
    const PAGE_START_Y = 45;
    const TAX = 20;
    const GROUP_INVOICE = [
        'Storage'           => [Charge::TYPE_STORAGE_PF, Charge::TYPE_STORAGE_ST],
        'Booking In'        => [Charge::TYPE_BOOKING_IN],
        'Orders'            => [Charge::TYPE_PICK],
        'OrdersPackaging'   => [Charge::TYPE_PACKAGING],
        'Culling'           => [Charge::TYPE_CULLING_PICK],
        'CullingPackaging'  => [Charge::TYPE_CULLING_PACKAGING],
        'Bundling'          => [Charge::TYPE_BUNDLING_PICK],
        'BundlingPackaging' => [Charge::TYPE_BUNDLING_PACKAGING],
        'Recurring'         => [Charge::TYPE_RECURRING]
    ];

    /** @var FPDFExtendedUTF8 */
    private $pdf;
    private $logo_name = null;
    private $address_from = [];
    private $address_to = [];
    private $alias;
    private $create_date;
    private $VAT_number;
    private $currency;
    private $page = 0;
    private $charges = [];
    private $amount = 0;
    private $warehouse_id;

    public function __construct(int $id)
    {
        [
            'sage_id' => $this->alias,
            'create_date' => $this->create_date,
            'billing_address' => $address_to,
            'VAT' => $this->VAT_number,
            'currency_id' => $currency_id,
            'warehouse_id' => $this->warehouse_id,
        ] = InvoiceBillingSuppliers::getSupplierBilling($id);
        $this->createAddress($address_to);
        $this->currency = Currencies::getExtend($currency_id);
        $this->charges = InvoiceBillingCharges::getCharges($id, $this->currency['symbol']);
        require(ROOT_PATH . VENDOR_PATH . '/FPDF/tfdpf/tfpdf.php');
        $this->pdf = new FPDFExtendedUTF8('P', 'pt', 'A4');
        return $this;
    }

    public function setLogoName(string $name): Invoice
    {
        $this->logo_name = $name;
        return $this;
    }

    public function setAddressFrom(array $address): Invoice
    {
        $this->address_from = $address;
        return $this;
    }

    protected function getFilename()
    {
        return sprintf("{$this->alias}-%03d-invoice-" . date('Ymd', $this->create_date), $this->warehouse_id);
    }

    protected function settings()
    {
        $this->pdf->SetMargins(30, 30, 30);
        $this->pdf->SetTitle($this->getFilename());
        $this->addPage();
        $this->pdf->W = $this->pdf->GetPageWidth() - 60;
    }

    protected function addPage()
    {
        $this->pdf->AddPage();
        $this->page++;
        $this->pdf->setFooter(true);
        $this->pdf->SetXY($this->pdf->GetPageWidth() - 70, -30);
        $this->pdf->SetFont('Roboto', '', 11);
        $this->pdf->Cell(30, 10, "Page {$this->page}", 0, 0, 'R');
        $this->pdf->SetXY(0, 0);
    }

    protected function addressFrom(int $x = 36, int $y = self::PAGE_START_Y)
    {
        $height_line_logo = 14;
        $this->pdf->AddFont('DejaVuB', '', 'DejaVuSans-Bold.ttf', true);
        $this->pdf->SetFont('DejaVuB', '', 7.5);
        $this->pdf->SetXY($x, $y);
        if (!empty($this->logo_name))
            $this->pdf->SetY($this->pdf->GetY() + $this->createLogo() + 9);
        if (!empty($this->address_from)) {
            $this->pdf->SetX(84);
            $this->pdf->MultiCell($this->pdf->W * 0.45, $height_line_logo, implode("\n", $this->address_from), 0, 'L');
        }
    }

    protected function addressTo(): int
    {
        $height_line = 16;
        $top = $this->pdf->GetY() + 20;
        $this->pdf->SetFont('Roboto', '', 10);
        $this->pdf->SetXY(42, $top);
        $this->pdf->MultiCell(30, $height_line, 'TO:', 0, 'L');
        $current_Y = $this->pdf->GetY();
        if (empty($this->address_to))
            return $current_Y;
        $this->pdf->SetFont('Roboto', '', 10);
        $this->pdf->SetXY(84, $top);
        $this->pdf->MultiCell($this->pdf->W * 0.45, $height_line, implode("\n", $this->address_to), 0, 'L');
        return max($current_Y, $this->pdf->GetY());
    }

    protected function invoice()
    {
        $height_line = 13;
        $this->pdf->SetFont('Roboto', '', 24);
        $this->pdf->SetXY($this->pdf->W * 0.67, 87);
        $this->pdf->Cell($this->pdf->W * 0.36, $height_line, 'Invoice', 0, 0, 'R');
        $this->pdf->SetFont('Roboto', '', 10);
        $this->pdf->SetXY($this->pdf->W * 0.668, 119);
        $this->pdf->MultiCell($this->pdf->W * 0.17, $height_line, "Account Reference\nTax Point Date\nCurrency", 0, 'L');
        $this->pdf->SetXY($this->pdf->W * 0.67, 119);
        $number = "{$this->alias}." . date('Ymd', $this->create_date);
        $date = date('Y-m-d', $this->create_date);
        $this->pdf->MultiCell($this->pdf->W * 0.36, $height_line, "$number\n$date\n" . $this->currency['name'], 0, 'R');
        return $this->pdf->GetY();
    }

    private function startPage(int &$y, bool $is_new = true)
    {
        if ($is_new) {
            if ($y <= $this->pdf->GetPageHeight() - 85)
                return;
            $this->addPage();
        }
        $this->pdf->SetFont('Roboto', 'B', 8);
        $y = $this->rowTable($is_new ? self::PAGE_START_Y : $y, ['Description', 'Qty', 'Price', 'Sub total'], []);
        $this->pdf->Line(35, $y + 8, $this->pdf->GetX(), $y + 8);
        $y += 16;
        $this->pdf->SetFont('Roboto', '', 8);
    }

    private function rowTable(int $y, array $row, array $details)
    {
        $x_new = $x = 35;
        $this->startPage($y);
        $y_new = $y;
        $width = [300, 50, 90, 85];
        $align = ['L', 'C', 'R', 'R'];
        $this->pdf->SetXY($x, $y);
        for ($i = 0; $i < 4; $i++) {
            $this->pdf->MultiCell($width[$i], 14, $row[$i], 0, $align[$i]);
            $y_new = max($y_new, $this->pdf->GetY());
            $this->pdf->SetXY($x_new += $width[$i], $y);
        }
        if ($details)
            foreach ($details as $detail) {
                $x_new = $x;
                $this->startPage($y_new);
                $this->pdf->SetXY($x_new + 30, $y_new);
                $y_detail = $y_new;
                for ($i = 0; $i < 4; $i++) {
                    $this->pdf->MultiCell($width[$i], 14, $detail[$i], 0, $align[$i]);
                    $y_detail = max($y_detail, $this->pdf->GetY());
                    $this->pdf->SetXY($x_new += $width[$i], $y_new);
                }
                $y_new = $y_detail;
            }
        return $y_new;
    }

    private function underTable(int $y, array $row)
    {
        $x = $x_new = 385;
        if ($y > $this->pdf->GetPageHeight() - 85) {
            $this->addPage();
            $y = self::PAGE_START_Y;
            $this->pdf->SetFont('Roboto', '', 8);
        }
        $y_new = $y;
        $width = [90, 85];
        $align = ['R', 'R'];
        $this->pdf->SetXY($x, $y);
        for ($i = 0; $i < 2; $i++) {
            $this->pdf->MultiCell($width[$i], 14, $row[$i], 0, $align[$i]);
            $y_new = max($y_new, $this->pdf->GetY());
            $this->pdf->SetXY($x_new += $width[$i], $y);
        }
        $this->pdf->Line($x, $y_new + 8, $this->pdf->GetX(), $y_new + 8);
        return $y_new + 16;
    }

    private function toCurrency(float $x, int $pos = 4)
    {
        return sprintf("%s %.{$pos}f", $this->currency['symbol'], $x);
    }

    protected function table(int $y)
    {
        $this->startPage($y, false);
        $sum = 0;
        foreach (self::GROUP_INVOICE as $group_charges) {
            $empty_group = true;
            foreach ($group_charges as $group_charge)
                foreach ($this->charges[$group_charge] ?? [] as $charge) {
                    $y = $this->rowTable($y, [$charge['name'], $charge['quantity'], $this->toCurrency($charge['value']), $this->toCurrency($charge['price'])], $charge['details'] ?? []);
                    $sum += $charge['price'];
                    if ($empty_group)
                        $empty_group = false;
                }
            if (!$empty_group) {
                $this->pdf->Line(35, $y + 8, $this->pdf->GetX(), $y + 8);
                $y += 16;
            }
        }
        $y = $this->underTable($y, ['Taxable amount', $this->toCurrency($sum)]);
        $y = $this->underTable($y, ['Tax rate', self::TAX . "%"]);
        $taxes = $sum*self::TAX/100;
        $y = $this->underTable($y, ['Total taxes', $this->toCurrency($taxes)]);
        $this->pdf->SetFont('Roboto', 'B', 9);
        $this->amount = $sum + $taxes;
        return $this->underTable($y, ['Total amount', $this->toCurrency($this->amount, 2)]);
    }

    protected function amountWords(int $y)
    {
        $amount = number_format($this->amount, 2, '.', '');
        $formatter = new NumberFormatter('en_EN', NumberFormatter::SPELLOUT);
        $value = explode('.', $amount);
        $integer_value  = (int) $value[0] !== 0 ? $formatter->format($value[0]) : 0;
        $fraction_value = isset($value[1]) ? $formatter->format($value[1]) : 0;
        $this->pdf->SetFont('Roboto', '', 8);
        $this->pdf->SetXY(35, $y);
        $in_word = sprintf("%s %s and %s %s.", ucfirst($integer_value), $this->currency['code'], $fraction_value, $this->currency['coins']);
        $this->pdf->MultiCell($this->pdf->W * 0.98, 14, $in_word, 0, 'L');
    }

    public function make()
    {
        $this->settings();
        $this->addressFrom();
        $current_Y_L = $this->addressTo();
        $current_Y_R = $this->invoice();
        $y = max($current_Y_L, $current_Y_R) + 38;
        if (!empty($this->charges))
            $y = $this->table($y) + 20;
        $this->amountWords($y);
        $this->pdf->Output("D", $this->getFilename() . ".pdf", true);
    }

    private function createLogo()
    {
        $temp = fopen(ROOT_PATH . COMMON_IMG . DIRECTORY_SEPARATOR . 'invoices' . DIRECTORY_SEPARATOR . $this->logo_name, 'r');
        $logo = stream_get_meta_data($temp)['uri'];
        $max_size = $this->pdf->W * 0.35;
        list($image_W, $image_H) = getimagesize($logo);
        if ($image_W > $max_size || $image_H > $max_size) {
            if ($image_H >= $image_W) {
                $image_W = ($max_size * $image_W) / $image_H;
                $image_H = $max_size;
            } else {
                $image_H = ($max_size * $image_H) / $image_W;
                $image_W = $max_size;
            }
        }
        $this->pdf->Image($logo, $this->pdf->GetX(), $this->pdf->GetY(), $image_W, $image_H, File::getImageType($logo));
        return $image_H;
    }

    private function createAddress($address_to)
    {
        if (empty($address_to))
            return;
        $address_to = json_decode($address_to, true);
        if (empty($address_to))
            return;
        $address_to = array_filter($address_to);
        $address_fields = ['address1', 'address2', 'address3', 'town', 'county', 'postcode', 'country_id'];
        $address = [];
        foreach ($address_fields as $field)
            if (!empty($address_to[$field]))
                $address[] = $address_to[$field];
        $this->address_to = array_filter([
            $address_to['name'] . (empty($address_to['contact']) ? '' : ", {$address_to['contact']}"),
            implode(', ', $address),
            !empty($this->VAT_number) ? "V.A.T. No {$this->VAT_number}" : '',
            $address_to['email']
        ]);
    }
}